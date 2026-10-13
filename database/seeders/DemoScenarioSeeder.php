<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\CourseClass;
use App\Models\Instructor;
use App\Models\InstructorLeave;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\ScheduleSubstitution;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoScenarioSeeder extends Seeder
{
    public const DEMO_DATE = '2026-10-25';

    public const DEMO_PREFIX = '[DEMO]';

    /**
     * Run the database seeds for local and testing environments only.
     */
    public function run(): void
    {
        if (app()->environment('production') && ! (bool) env('DEMO_MODE', false)) {
            $this->command?->error('Pemberitahuan Keamanan: DemoScenarioSeeder dilarang dijalankan di lingkungan produksi jika DEMO_MODE tidak aktif!');

            return;
        }

        // Bersihkan data demo terdahulu agar proses selalu idempotent dan bersih
        self::clean();

        $this->command?->info('Menyiapkan Data Skenario Demonstrasi & Pengujian PALCOM AI Scheduler...');

        // 1. Pastikan Pengguna Admin Tersedia tanpa hardcoded default password
        $adminEmail = config('auth.admin_emails.0', 'admin@palcomtech.ac.id');
        $admin = User::firstWhere('email', $adminEmail);

        if (! $admin) {
            $admin = User::create([
                'name' => 'Admin BAAK PalComTech',
                'email' => $adminEmail,
                'password' => Hash::make(env('DEMO_ADMIN_PASSWORD', Str::random(32))),
                'email_verified_at' => now(),
            ]);
        }

        // 2. Siapkan Instruktur Master (firstOrCreate: tidak mengubah master data yang sudah ada)
        $wahyu = Instructor::firstOrCreate(['name' => 'Wahyu'], ['status' => 'active']);
        $kanero = Instructor::firstOrCreate(['name' => 'Kanero'], ['status' => 'active']);
        $dina = Instructor::firstOrCreate(['name' => 'Dina Oktavia'], ['status' => 'active']);
        $budi = Instructor::firstOrCreate(['name' => 'Budi Santoso'], ['status' => 'active']);
        $rizky = Instructor::firstOrCreate(['name' => 'Rizky Pratama'], ['status' => 'active']);

        // 3. Siapkan Kualifikasi / Skill Instruktur (firstOrCreate: tidak menimpa level skill yang sudah ada)
        // Kanero: Ahli Excel & Word (Kandidat Valid Skenario 2)
        $kanero->skills()->firstOrCreate(['skill' => 'Microsoft Excel'], ['level' => 'advanced']);
        $kanero->skills()->firstOrCreate(['skill' => 'Microsoft Word'], ['level' => 'advanced']);

        // Dina: Hanya Desain Grafis (Tidak Memenuhi Kompetensi Skenario 3)
        $dina->skills()->firstOrCreate(['skill' => 'Desain Grafis'], ['level' => 'advanced']);

        // Budi: Punya skill Excel (namun akan dibuat bentrok di Skenario 4)
        $budi->skills()->firstOrCreate(['skill' => 'Microsoft Excel'], ['level' => 'intermediate']);

        // Wahyu: Excel, Word, Web
        $wahyu->skills()->firstOrCreate(['skill' => 'Microsoft Excel'], ['level' => 'advanced']);
        $wahyu->skills()->firstOrCreate(['skill' => 'Microsoft Word'], ['level' => 'advanced']);
        $wahyu->skills()->firstOrCreate(['skill' => 'Pemrograman Web'], ['level' => 'advanced']);

        // 4. Siapkan Ruangan (firstOrCreate: menjaga kapasitas dan status ruangan operasional asli)
        $lab1 = Room::firstOrCreate(
            ['name' => 'Lab 1'],
            ['capacity' => 35, 'status' => 'available']
        );
        $lab2 = Room::firstOrCreate(
            ['name' => 'Lab 2'],
            ['capacity' => 30, 'status' => 'available']
        );
        // Skenario 5: Ruangan kapasitas tidak mencukupi / tidak available
        $labDemoSmall = Room::updateOrCreate(
            ['name' => self::DEMO_PREFIX.' Lab 3 Mini'],
            ['capacity' => 15, 'status' => 'maintenance']
        );

        // 5. Siapkan Mata Kuliah & Kelas
        $classExcel = CourseClass::updateOrCreate(
            ['name' => self::DEMO_PREFIX.' Excel Bisnis'],
            ['subject' => 'Microsoft Excel', 'student_count' => 25]
        );
        $classWord = CourseClass::updateOrCreate(
            ['name' => self::DEMO_PREFIX.' Word Administrasi'],
            ['subject' => 'Microsoft Word', 'student_count' => 20]
        );
        $classWeb = CourseClass::updateOrCreate(
            ['name' => self::DEMO_PREFIX.' Pemrograman Web Lanjut'],
            ['subject' => 'Pemrograman Web', 'student_count' => 30]
        );

        // -------------------------------------------------------------
        // SKENARIO 1, 2, 3, 4: Izin Wahyu dengan Beberapa Kelas Terdampak
        // -------------------------------------------------------------
        // Jadwal A: 13:00 - 15:00 (Excel di Lab 1, pengajar awal Wahyu)
        $scheduleA = Schedule::updateOrCreate(
            [
                'course_class_id' => $classExcel->id,
                'date' => self::DEMO_DATE,
                'start_time' => '13:00:00',
            ],
            [
                'instructor_id' => $wahyu->id,
                'room_id' => $lab1->id,
                'end_time' => '15:00:00',
                'status' => 'scheduled',
            ]
        );

        // Jadwal B: 16:00 - 18:00 (Word di Lab 1, pengajar awal Wahyu)
        $scheduleB = Schedule::updateOrCreate(
            [
                'course_class_id' => $classWord->id,
                'date' => self::DEMO_DATE,
                'start_time' => '16:00:00',
            ],
            [
                'instructor_id' => $wahyu->id,
                'room_id' => $lab1->id,
                'end_time' => '18:00:00',
                'status' => 'scheduled',
            ]
        );

        // SKENARIO 4: Jadwal Bentrok Budi Santoso (13:00 - 15:00 di Lab 2)
        Schedule::updateOrCreate(
            [
                'course_class_id' => $classExcel->id,
                'instructor_id' => $budi->id,
                'date' => self::DEMO_DATE,
                'start_time' => '13:00:00',
            ],
            [
                'room_id' => $lab2->id,
                'end_time' => '15:00:00',
                'status' => 'scheduled',
            ]
        );

        // Pengajuan Izin Wahyu (Terdampak Jadwal A dan B)
        $leaveWahyu = InstructorLeave::updateOrCreate(
            [
                'instructor_id' => $wahyu->id,
                'date' => self::DEMO_DATE,
                'start_time' => '13:00:00',
                'end_time' => '18:00:00',
            ],
            [
                'reason' => self::DEMO_PREFIX.' Urusan Dinas Kampus ke Luar Kota',
                'status' => 'pending',
            ]
        );

        // -------------------------------------------------------------
        // SKENARIO 5: Ruangan Tidak Tersedia / Kapasitas Kurang
        // -------------------------------------------------------------
        $scheduleRoomIssue = Schedule::updateOrCreate(
            [
                'course_class_id' => $classWeb->id,
                'date' => self::DEMO_DATE,
                'start_time' => '08:00:00',
            ],
            [
                'instructor_id' => $wahyu->id,
                'room_id' => $labDemoSmall->id, // Kapasitas 15 < 30 & status maintenance
                'end_time' => '10:00:00',
                'status' => 'scheduled',
            ]
        );

        // Izin Wahyu Pagi (terdampak jadwal dengan kendala ruangan)
        $leaveRoomIssue = InstructorLeave::updateOrCreate(
            [
                'instructor_id' => $wahyu->id,
                'date' => self::DEMO_DATE,
                'start_time' => '08:00:00',
                'end_time' => '11:00:00',
            ],
            [
                'reason' => self::DEMO_PREFIX.' Izin Sesi Pagi - Kendala Ruangan',
                'status' => 'pending',
            ]
        );

        // -------------------------------------------------------------
        // SKENARIO 6: Pengajuan Izin Tanpa Jadwal Terdampak
        // -------------------------------------------------------------
        // Rizky Pratama tidak mengajar di tanggal Demo
        $leaveNoAffected = InstructorLeave::updateOrCreate(
            [
                'instructor_id' => $rizky->id,
                'date' => self::DEMO_DATE,
                'start_time' => '08:00:00',
                'end_time' => '12:00:00',
            ],
            [
                'reason' => self::DEMO_PREFIX.' Izin Tanpa Kelas Terdampak',
                'status' => 'pending',
            ]
        );

        // -------------------------------------------------------------
        // SKENARIO 7: Approval yang Berhasil
        // -------------------------------------------------------------
        // Siapkan jadwal tersendiri untuk demonstrasi persetujuan final
        $classExcelApproved = CourseClass::updateOrCreate(
            ['name' => self::DEMO_PREFIX.' Excel Pagi Disetujui'],
            ['subject' => 'Microsoft Excel', 'student_count' => 20]
        );

        $scheduleApproved = Schedule::updateOrCreate(
            [
                'course_class_id' => $classExcelApproved->id,
                'date' => self::DEMO_DATE,
                'start_time' => '10:00:00',
            ],
            [
                'instructor_id' => $kanero->id, // Sudah diperbarui ke Kanero
                'room_id' => $lab1->id,
                'end_time' => '12:00:00',
                'status' => 'scheduled',
            ]
        );

        $leaveApproved = InstructorLeave::updateOrCreate(
            [
                'instructor_id' => $wahyu->id,
                'date' => self::DEMO_DATE,
                'start_time' => '10:00:00',
                'end_time' => '12:00:00',
            ],
            [
                'reason' => self::DEMO_PREFIX.' Izin Penggantian Berhasil Disetujui',
                'status' => 'pending',
            ]
        );

        $subApproved = ScheduleSubstitution::updateOrCreate(
            [
                'instructor_leave_id' => $leaveApproved->id,
                'schedule_id' => $scheduleApproved->id,
            ],
            [
                'original_instructor_id' => $wahyu->id,
                'replacement_instructor_id' => $kanero->id,
                'status' => 'approved',
                'decision_by' => $admin->id,
                'decision_at' => now(),
                'notes' => self::DEMO_PREFIX.' Instruktur pengganti Kanero disetujui resmi oleh admin BAAK.',
            ]
        );

        // Catat di Activity Log
        $activityService = app(ActivityLogService::class);
        $activityService->logApproval($subApproved, $scheduleApproved, $admin);
        $activityService->logScheduleUpdated($scheduleApproved, $kanero, $admin, $leaveApproved->id);

        // -------------------------------------------------------------
        // SKENARIO 8: Reject Beserta Alasannya
        // -------------------------------------------------------------
        $classWordRejected = CourseClass::updateOrCreate(
            ['name' => self::DEMO_PREFIX.' Word Sore Ditolak'],
            ['subject' => 'Microsoft Word', 'student_count' => 22]
        );

        $scheduleRejected = Schedule::updateOrCreate(
            [
                'course_class_id' => $classWordRejected->id,
                'date' => self::DEMO_DATE,
                'start_time' => '19:00:00',
            ],
            [
                'instructor_id' => $wahyu->id, // Tetap Wahyu
                'room_id' => $lab1->id,
                'end_time' => '21:00:00',
                'status' => 'scheduled',
            ]
        );

        $leaveRejected = InstructorLeave::updateOrCreate(
            [
                'instructor_id' => $wahyu->id,
                'date' => self::DEMO_DATE,
                'start_time' => '19:00:00',
                'end_time' => '21:00:00',
            ],
            [
                'reason' => self::DEMO_PREFIX.' Izin dengan Usulan Pengganti Ditolak',
                'status' => 'pending',
            ]
        );

        $rejectionReason = self::DEMO_PREFIX.' Usulan instruktur pengganti belum dapat disetujui karena beban mengajar sudah melampaui batas maksimum SKS mingguan.';

        $subRejected = ScheduleSubstitution::updateOrCreate(
            [
                'instructor_leave_id' => $leaveRejected->id,
                'schedule_id' => $scheduleRejected->id,
            ],
            [
                'original_instructor_id' => $wahyu->id,
                'replacement_instructor_id' => null,
                'status' => 'rejected',
                'decision_by' => $admin->id,
                'decision_at' => now(),
                'rejection_reason' => $rejectionReason,
            ]
        );

        $activityService->logRejection($subRejected, $scheduleRejected, $rejectionReason, $admin);

        // -------------------------------------------------------------
        // SKENARIO 9: Skenario Gemini Fallback
        // -------------------------------------------------------------
        // Catat activity log simulasi untuk showcase fallback
        ActivityLog::updateOrCreate(
            [
                'type' => 'ai',
                'instructor_leave_id' => $leaveWahyu->id,
                'title' => 'Simulasi Fallback AI: Layanan Gemini Dialihkan ke Deterministik',
            ],
            [
                'state' => 'fallback',
                'actor' => 'Gemini AI',
                'description' => self::DEMO_PREFIX.' [SIMULASI] Layanan Gemini AI disimulasikan mengalami timeout atau batasan kuota. Rekomendasi dialihkan otomatis ke hasil deterministik sistem.',
                'subject' => "Pengajuan Izin #{$leaveWahyu->id}",
                'metadata' => [
                    'leave_id' => $leaveWahyu->id,
                    'status' => 'fallback',
                    'confidence_score' => null,
                    'fallback_used' => true,
                    'simulation' => true,
                    'total_analyzed' => 2,
                ],
            ]
        );

        $this->command?->info('Seluruh 9 Skenario Demonstrasi berhasil disiapkan dengan aman!');
    }

    /**
     * Membersihkan data demo secara aman tanpa menyentuh data master/pengguna operasional.
     */
    public static function clean(): int
    {
        $prefix = self::DEMO_PREFIX;

        // 1. Hapus substitusi dan activity log yang terkait dengan pengajuan izin demo
        $demoLeaveIds = InstructorLeave::where('reason', 'like', "%{$prefix}%")->pluck('id');

        ActivityLog::whereIn('instructor_leave_id', $demoLeaveIds)
            ->orWhere('description', 'like', "%{$prefix}%")
            ->orWhere('subject', 'like', "%{$prefix}%")
            ->delete();

        ScheduleSubstitution::whereIn('instructor_leave_id', $demoLeaveIds)
            ->orWhere('notes', 'like', "%{$prefix}%")
            ->orWhere('rejection_reason', 'like', "%{$prefix}%")
            ->delete();

        // 2. Hapus jadwal demo yang terikat pada kelas demo
        $demoClassIds = CourseClass::where('name', 'like', "%{$prefix}%")->pluck('id');
        Schedule::whereIn('course_class_id', $demoClassIds)->delete();

        // 3. Hapus kelas demo
        CourseClass::where('name', 'like', "%{$prefix}%")->delete();

        // 4. Hapus pengajuan izin demo
        $deletedLeavesCount = InstructorLeave::whereIn('id', $demoLeaveIds)->delete();

        // 5. Hapus ruangan demo (jika ada)
        Room::where('name', 'like', "%{$prefix}%")->delete();

        return $deletedLeavesCount;
    }
}
