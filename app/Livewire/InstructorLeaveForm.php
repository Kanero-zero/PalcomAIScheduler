<?php

namespace App\Livewire;

use App\Models\Instructor;
use App\Models\InstructorLeave;
use App\Services\Scheduling\GeminiSchedulingAdvisor;
use App\Services\Scheduling\SchedulingEngine;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

class InstructorLeaveForm extends Component
{
    /**
     * Form state sesuai dengan spesifikasi API_CONTRACT_SCHEDULER.md.
     *
     * @var array{instructor_id: int|null, date: string, start_time: string, end_time: string, reason: string|null}
     */
    public array $form = [
        'instructor_id' => null,
        'date' => '',
        'start_time' => '',
        'end_time' => '',
        'reason' => '',
    ];

    /**
     * Hasil evaluasi penjadwalan sesuai struktur kontrak data API Contract.
     *
     * @var array<string, mixed>|null
     */
    public ?array $schedulingResult = null;

    /**
     * ID pengajuan izin yang berhasil disimpan ke database.
     */
    public ?int $submittedLeaveId = null;

    /**
     * Pesan notifikasi status atau feedback pengguna.
     */
    public ?string $feedbackMessage = null;

    /**
     * Aturan validasi server-side sesuai API_CONTRACT_SCHEDULER.md.
     *
     * @return array<string, list<string>>
     */
    protected function rules(): array
    {
        return [
            'form.instructor_id' => ['required', 'integer', 'exists:instructors,id'],
            'form.date' => ['required', 'date'],
            'form.start_time' => ['required', 'date_format:H:i'],
            'form.end_time' => ['required', 'date_format:H:i', 'after:form.start_time'],
            'form.reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Label atribut untuk pesan kesalahan validasi dalam Bahasa Indonesia.
     *
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'form.instructor_id' => 'Instruktur',
            'form.date' => 'Tanggal izin',
            'form.start_time' => 'Jam mulai',
            'form.end_time' => 'Jam selesai',
            'form.reason' => 'Alasan izin',
        ];
    }

    /**
     * Pesan validasi kustom.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'form.instructor_id.required' => 'Instruktur wajib dipilih.',
            'form.instructor_id.exists' => 'Instruktur yang dipilih tidak valid.',
            'form.date.required' => 'Tanggal izin wajib diisi.',
            'form.start_time.required' => 'Jam mulai wajib diisi.',
            'form.end_time.required' => 'Jam selesai wajib diisi.',
            'form.end_time.after' => 'Jam selesai harus setelah jam mulai.',
            'form.start_time.date_format' => 'Format jam mulai harus HH:mm (contoh: 13:00).',
            'form.end_time.date_format' => 'Format jam selesai harus HH:mm (contoh: 18:00).',
        ];
    }

    /**
     * Daftar instruktur aktif untuk dropdown, urut berdasarkan nama.
     *
     * @return Collection<int, Instructor>
     */
    public function getInstructorsProperty(): Collection
    {
        return Instructor::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Action untuk memvalidasi, menyimpan pengajuan izin, dan mengeksekusi SchedulingEngine.
     *
     * @return array<string, mixed>|null
     */
    public function submitLeave(): ?array
    {
        $this->feedbackMessage = null;
        $this->schedulingResult = null;
        $this->submittedLeaveId = null;

        // Normalisasi format waktu jika frontend mengirim HH:mm:ss
        if (! empty($this->form['start_time']) && strlen($this->form['start_time']) > 5) {
            $this->form['start_time'] = substr($this->form['start_time'], 0, 5);
        }
        if (! empty($this->form['end_time']) && strlen($this->form['end_time']) > 5) {
            $this->form['end_time'] = substr($this->form['end_time'], 0, 5);
        }

        $validated = $this->validate();

        $instructorId = (int) $this->form['instructor_id'];
        $date = trim($this->form['date']);
        $startTime = trim($this->form['start_time']);
        $endTime = trim($this->form['end_time']);
        $reason = ! empty($this->form['reason']) ? trim($this->form['reason']) : null;

        // Pencegahan pengajuan izin duplikat pada tanggal dan rentang waktu yang sama
        $duplicateLeave = InstructorLeave::query()
            ->where('instructor_id', $instructorId)
            ->whereDate('date', $date)
            ->where('status', '!=', 'rejected')
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->first();

        if ($duplicateLeave) {
            $this->addError(
                'form.instructor_id',
                "Instruktur ini sudah memiliki pengajuan izin ({$duplicateLeave->status}) pada tanggal dan jam tersebut (ID Izin: #{$duplicateLeave->id})."
            );

            return null;
        }

        // 5. Simpan pengajuan ke tabel instructor_leaves dengan status awal 'pending'
        // Status awal 'pending' mencerminkan alur persetujuan: izin belum final dan rekomendasi belum diterapkan ke jadwal.
        $leave = InstructorLeave::create([
            'instructor_id' => $instructorId,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'reason' => $reason,
            'status' => 'pending',
        ]);

        $this->submittedLeaveId = $leave->id;

        // 6. Jalankan evaluasi deterministik SchedulingEngine
        // Evaluasi ini hanya menghasilkan analisis dan rekomendasi kandidat, TIDAK mengubah data jadwal mengajar.
        $engine = app(SchedulingEngine::class);
        $evaluation = $engine->evaluateLeave($leave);

        // 7. Simpan hasil evaluasi sesuai struktur data dalam API Contract
        $this->schedulingResult = $evaluation->toArray();

        // 8. Pesan feedback informatif untuk pengguna/admin
        if ($evaluation->totalAffectedSchedules === 0) {
            $this->feedbackMessage = 'Pengajuan izin berhasil dicatat (status: pending). Tidak ada jadwal kelas yang terdampak pada rentang waktu ini.';
        } elseif ($evaluation->allSchedulesResolved) {
            $this->feedbackMessage = "Pengajuan izin berhasil dicatat (status: pending). Ditemukan {$evaluation->totalAffectedSchedules} kelas terdampak dan seluruhnya berhasil dicarikan solusi rekomendasi.";
        } else {
            $this->feedbackMessage = "Pengajuan izin berhasil dicatat (status: pending). Ditemukan {$evaluation->totalAffectedSchedules} kelas terdampak ({$evaluation->totalResolvedSchedules} terselesaikan). Beberapa kelas membutuhkan perhatian atau penyesuaian admin.";
        }

        return $this->schedulingResult;
    }

    /**
     * Action untuk meminta analisis mendalam dan penjelasan rekomendasi dari Google Gemini AI.
     * Hanya dijalankan jika admin meminta secara eksplisit, bukan otomatis.
     *
     * @return array<string, mixed>|null
     */
    public function analyzeWithAi(): ?array
    {
        if (! $this->schedulingResult || ! $this->submittedLeaveId) {
            return null;
        }

        $leave = InstructorLeave::find($this->submittedLeaveId);
        if (! $leave) {
            return null;
        }

        $engine = app(SchedulingEngine::class);
        $advisor = app(GeminiSchedulingAdvisor::class);

        $deterministicResult = $engine->evaluateLeave($leave);
        $enhancedResult = $advisor->enhanceEvaluation($deterministicResult);

        $this->schedulingResult = $enhancedResult->toArray();

        $this->feedbackMessage = 'Rekomendasi berhasil dianalisis dan diperkaya dengan Google Gemini 3.5 Flash-Lite.';

        return $this->schedulingResult;
    }

    /**
     * Reset seluruh form input dan hasil evaluasi.
     */
    public function resetForm(): void
    {
        $this->form = [
            'instructor_id' => null,
            'date' => '',
            'start_time' => '',
            'end_time' => '',
            'reason' => '',
        ];
        $this->resetErrorBag();
        $this->resetValidation();
        $this->schedulingResult = null;
        $this->submittedLeaveId = null;
        $this->feedbackMessage = null;
    }

    public function render(): View
    {
        return view('livewire.instructor-leave-form', [
            'instructors' => $this->instructors,
        ]);
    }
}
