<?php

namespace App\Services\Room;

use App\Models\Competency;
use App\Models\Room;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class RoomConflictGuard
{
    /**
     * Whitelist status resmi yang mengunci ruangan.
     *
     * @var list<string>
     */
    public const OFFICIAL_STATUSES = ['scheduled'];

    /**
     * Status non-resmi atau proposal yang tidak mengunci ruangan.
     *
     * @var list<string>
     */
    public const NON_OFFICIAL_STATUSES = ['draft', 'pending', 'proposal', 'tentative', 'cancelled'];

    /**
     * Zona waktu operasional kampus untuk penentuan batas waktu jadwal.
     */
    public const TIMEZONE = 'Asia/Jakarta';

    /**
     * Validasi apakah perubahan ruangan melanggar jadwal resmi mendatang.
     *
     * @param  Room  $room  Record ruangan di basis data sebelum diperbarui
     * @param  array{name: string, department: string, capacity: int, status: string, notes?: string|null}  $newData  Data baru yang akan disimpan
     *
     * @throws ValidationException Jika perubahan akan membuat satu atau lebih reservasi resmi menjadi tidak valid.
     */
    public function validateRoomUpdate(Room $room, array $newData): void
    {
        // Jika hanya nama dan/atau catatan yang berubah, perubahan selalu diperbolehkan
        $statusChanged = $room->status !== $newData['status'];
        $departmentChanged = $room->department !== $newData['department'];
        $capacityDecreased = (int) $newData['capacity'] < (int) $room->capacity;

        if (! $statusChanged && ! $departmentChanged && ! $capacityDecreased) {
            return;
        }

        // Ambil seluruh jadwal masa depan yang terkait dengan ruangan ini berdasarkan WIB
        $upcomingSchedules = $this->getUpcomingSchedulesForRoom($room->id);

        if ($upcomingSchedules->isEmpty()) {
            return;
        }

        // Fail-Closed R22: Periksa apakah ada jadwal masa depan dengan status tidak dikenal / unverified
        foreach ($upcomingSchedules as $schedule) {
            $status = strtolower(trim((string) $schedule->status));

            // Jika status tidak ada di allowlist resmi dan bukan pula status non-resmi yang dikenal
            if (! in_array($status, self::OFFICIAL_STATUSES, true) && ! in_array($status, self::NON_OFFICIAL_STATUSES, true)) {
                $formattedDate = $schedule->date ? Carbon::parse($schedule->date)->format('d/m/Y') : '-';
                throw ValidationException::withMessages([
                    'status' => "Terdapat jadwal pertemuan mendatang pada {$formattedDate} dengan status '{$schedule->status}' yang belum dapat diverifikasi secara tepercaya. Perubahan operasional ruangan ditolak (Fail-Closed) demi keamanan jadwal.",
                ]);
            }
        }

        // Filter hanya jadwal resmi (official approved)
        $officialSchedules = $upcomingSchedules->filter(function (Schedule $schedule): bool {
            return in_array(strtolower(trim((string) $schedule->status)), self::OFFICIAL_STATUSES, true);
        });

        if ($officialSchedules->isEmpty()) {
            // Hanya ada draft/pending/cancelled -> tidak mengunci ruangan
            return;
        }

        // 1. Constraint Status: Perubahan ke maintenance atau unavailable
        if ($statusChanged && in_array($newData['status'], ['maintenance', 'unavailable'], true)) {
            $conflictSchedule = $officialSchedules->first();
            $className = $conflictSchedule->courseClass?->name ?? 'Kelas #'.$conflictSchedule->course_class_id;
            $formattedDate = $conflictSchedule->date ? Carbon::parse($conflictSchedule->date)->format('d/m/Y') : '-';
            $timeRange = substr((string) $conflictSchedule->start_time, 0, 5).'–'.substr((string) $conflictSchedule->end_time, 0, 5).' WIB';
            $targetLabel = $newData['status'] === 'maintenance' ? 'Maintenance / Perawatan' : 'Tidak Operasional';

            throw ValidationException::withMessages([
                'status' => "Perubahan status ruangan menjadi '{$targetLabel}' ditolak karena terdapat {$officialSchedules->count()} jadwal/pertemuan resmi mendatang di ruangan ini (contoh: {$className} pada {$formattedDate} jam {$timeRange}).",
            ]);
        }

        // 2. Constraint Bidang: Perubahan computer <-> speakout atau null -> bidang
        if ($departmentChanged) {
            foreach ($officialSchedules as $schedule) {
                $class = $schedule->courseClass;

                // Fail-Closed jika relasi kelas tidak ada atau subject kosong
                if (! $class || empty(trim((string) $class->subject))) {
                    $formattedDate = $schedule->date ? Carbon::parse($schedule->date)->format('d/m/Y') : '-';
                    throw ValidationException::withMessages([
                        'department' => "Terdapat jadwal pertemuan resmi mendatang pada {$formattedDate} dengan relasi kelas yang tidak valid. Perubahan bidang ruangan ditolak (Fail-Closed).",
                    ]);
                }

                $subjectNormalized = mb_strtolower(trim($class->subject));

                // Cari di katalog kompetensi yang aktif menggunakan pencocokan pasti LOWER(TRIM())
                $matches = Competency::where('is_active', true)
                    ->whereRaw('LOWER(TRIM(name)) = ?', [$subjectNormalized])
                    ->get();

                if ($matches->isEmpty()) {
                    // Fail-Closed: Materi tidak terdaftar atau non-aktif di katalog
                    $formattedDate = $schedule->date ? Carbon::parse($schedule->date)->format('d/m/Y') : '-';
                    throw ValidationException::withMessages([
                        'department' => "Perubahan bidang ruangan ditolak (Fail-Closed): Kelas resmi mendatang '{$class->name}' pada {$formattedDate} memiliki mata pelajaran '{$class->subject}' yang belum terdaftar atau tidak aktif di Master Katalog Kompetensi.",
                    ]);
                }

                // Jika ditemukan duplikasi entri kompetensi (baik berbeda bidang maupun bidang sama), pemetaan tidak dapat dipastikan unik
                if ($matches->count() > 1) {
                    throw ValidationException::withMessages([
                        'department' => "Perubahan bidang ruangan ditolak (Fail-Closed): Ditemukan duplikasi entri kompetensi untuk mata pelajaran '{$class->subject}' pada katalog. Pemetaan bidang tidak dapat dipastikan secara unik.",
                    ]);
                }

                $classDepartment = $matches->first()->department;

                if ($classDepartment !== $newData['department']) {
                    $newDeptLabel = $newData['department'] === 'computer' ? 'Komputer' : 'SpeakOut';
                    $classDeptLabel = $classDepartment === 'computer' ? 'Komputer' : 'SpeakOut';
                    $formattedDate = $schedule->date ? Carbon::parse($schedule->date)->format('d/m/Y') : '-';
                    $timeRange = substr((string) $schedule->start_time, 0, 5).'–'.substr((string) $schedule->end_time, 0, 5).' WIB';

                    throw ValidationException::withMessages([
                        'department' => "Perubahan bidang ruangan menjadi '{$newDeptLabel}' ditolak karena terdapat jadwal resmi kelas '{$class->name}' ({$classDeptLabel}) pada {$formattedDate} jam {$timeRange}.",
                    ]);
                }
            }
        }

        // 3. Constraint Kapasitas: Penurunan kapasitas di bawah jumlah siswa
        if ($capacityDecreased) {
            foreach ($officialSchedules as $schedule) {
                $class = $schedule->courseClass;

                // Fail-Closed: Jika data kelas tidak ditemukan atau student_count bernilai null
                if (! $class || $class->student_count === null) {
                    $formattedDate = $schedule->date ? Carbon::parse($schedule->date)->format('d/m/Y') : '-';
                    throw ValidationException::withMessages([
                        'capacity' => "Penurunan kapasitas ruangan ditolak (Fail-Closed): Terdapat jadwal resmi pada {$formattedDate} dengan data jumlah siswa kelas yang tidak dapat diverifikasi.",
                    ]);
                }

                $studentCount = (int) $class->student_count;

                if ($studentCount > (int) $newData['capacity']) {
                    $formattedDate = $schedule->date ? Carbon::parse($schedule->date)->format('d/m/Y') : '-';
                    $timeRange = substr((string) $schedule->start_time, 0, 5).'–'.substr((string) $schedule->end_time, 0, 5).' WIB';

                    throw ValidationException::withMessages([
                        'capacity' => "Penurunan kapasitas menjadi {$newData['capacity']} siswa ditolak karena kelas resmi '{$class->name}' pada {$formattedDate} jam {$timeRange} memiliki {$studentCount} siswa.",
                    ]);
                }
            }
        }
    }

    /**
     * Dapatkan semua jadwal mendatang untuk ruangan tertentu berdasarkan waktu operasional Asia/Jakarta (WIB).
     *
     * @return Collection<int, Schedule>
     */
    public function getUpcomingSchedulesForRoom(int $roomId): Collection
    {
        $nowWib = Carbon::now(self::TIMEZONE);
        $today = $nowWib->toDateString();
        $currentTime = $nowWib->format('H:i:s');

        return Schedule::query()
            ->with('courseClass')
            ->where('room_id', $roomId)
            ->where(function (Builder $query) use ($today, $currentTime): void {
                $query->whereDate('date', '>', $today)
                    ->orWhere(function (Builder $sub) use ($today, $currentTime): void {
                        $sub->whereDate('date', $today)
                            ->where('end_time', '>', $currentTime);
                    });
            })
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
    }
}
