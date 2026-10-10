<?php

namespace App\Livewire;

use App\Models\InstructorLeave;
use App\Services\Scheduling\SchedulingEngine;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class AiScheduler extends Component
{
    public ?int $selectedLeaveId = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $result = null;

    /**
     * Pesan notifikasi status atau feedback pengguna terkait evaluasi.
     */
    public ?string $feedbackMessage = null;

    public function mount(): void
    {
        $defaultLeave = InstructorLeave::with('instructor')->first();
        if ($defaultLeave) {
            $this->selectedLeaveId = $defaultLeave->id;
            $this->runScheduler();
        }
    }

    public function selectLeave(int $leaveId): void
    {
        $this->selectedLeaveId = $leaveId;
        $this->runScheduler();
    }

    public function updatedSelectedLeaveId(): void
    {
        $this->runScheduler();
    }

    /**
     * Menjalankan evaluasi penjadwalan deterministik sebagai hasil utama.
     * Reset seluruh hasil AI terdahulu setiap kali evaluasi dijalankan ulang atau izin diganti.
     */
    public function runScheduler(): void
    {
        $this->feedbackMessage = null;

        if (! $this->selectedLeaveId) {
            $this->result = null;

            return;
        }

        $leave = InstructorLeave::with('instructor')->find($this->selectedLeaveId);
        if (! $leave) {
            $this->result = null;

            return;
        }

        $engine = app(SchedulingEngine::class);
        $evaluation = $engine->evaluateLeave($leave);
        $this->result = $evaluation->toArray();
    }

    /**
     * Meminta analisis rekomendasi lanjutan menggunakan Google Gemini 3.5 Flash-Lite.
     * Hanya dieksekusi saat admin secara eksplisit menekan tombol Gemini AI.
     *
     * @return array<string, mixed>|null
     */
    public function analyzeWithAi(): ?array
    {
        if (! $this->selectedLeaveId) {
            return null;
        }

        $leave = InstructorLeave::with('instructor')->find($this->selectedLeaveId);
        if (! $leave) {
            return null;
        }

        $engine = app(SchedulingEngine::class);
        $evaluation = $engine->evaluateWithAi($leave);
        $this->result = $evaluation->toArray();

        if ($evaluation->aiSummary && ($evaluation->aiSummary['fallback_used'] ?? false)) {
            $this->feedbackMessage = 'Layanan Gemini AI tidak dapat dijangkau. Rekomendasi tetap menggunakan hasil deterministik (Mode Fallback).';
        } else {
            $this->feedbackMessage = 'Rekomendasi berhasil dianalisis dengan Google Gemini 3.5 Flash-Lite.';
        }

        return $this->result;
    }

    public function render(): View
    {
        $leaves = InstructorLeave::with('instructor')->orderByDesc('date')->get();

        return view('livewire.ai-scheduler', [
            'leaves' => $leaves,
        ]);
    }
}
