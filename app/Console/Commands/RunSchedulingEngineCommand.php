<?php

namespace App\Console\Commands;

use App\Models\InstructorLeave;
use App\Services\Scheduling\SchedulingEngine;
use Illuminate\Console\Command;

class RunSchedulingEngineCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schedule:evaluate {leave_id? : ID pengajuan izin instruktur}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Jalankan Scheduling Engine deterministik untuk mencari instruktur pengganti yang valid';

    /**
     * Execute the console command.
     */
    public function handle(SchedulingEngine $engine): int
    {
        $leaveId = $this->argument('leave_id');

        $leave = $leaveId
            ? InstructorLeave::with('instructor')->find($leaveId)
            : InstructorLeave::with('instructor')->first();

        if (! $leave) {
            $this->error('Data pengajuan izin instruktur tidak ditemukan di database.');

            return self::FAILURE;
        }

        $this->info('===========================================================');
        $this->info('       PALCOM AI SCHEDULER - DETERMINISTIC ENGINE          ');
        $this->info('===========================================================');
        $this->line("Instruktur Izin : <comment>{$leave->instructor->name}</comment> (ID: {$leave->instructor_id})");
        $this->line("Tanggal Izin    : <comment>{$engine->normalizeDate($leave->date)}</comment>");
        $this->line("Waktu Izin      : <comment>{$leave->start_time} - {$leave->end_time}</comment>");
        $this->line("Alasan          : <comment>{$leave->reason}</comment>");
        $this->line('-----------------------------------------------------------');

        $result = $engine->evaluateLeave($leave);

        $this->info("Menemukan {$result->totalAffectedSchedules} kelas yang terdampak:");
        $this->newLine();

        foreach ($result->affectedSchedules as $index => $sched) {
            $num = $index + 1;
            $statusBadge = match ($sched->status) {
                'resolved' => '<fg=green>[RESOLVED]</>',
                'room_issue' => '<fg=red>[MASALAH RUANGAN]</>',
                'no_candidate' => '<fg=yellow>[TIDAK ADA PENGGANTI]</>',
                default => '<fg=red>[UNRESOLVED]</>',
            };

            $this->line("{$statusBadge} <options=bold,underscore>#{$num}. Kelas: {$sched->className} ({$sched->subject})</>");
            $this->line("   Waktu   : {$sched->startTime} - {$sched->endTime}");

            if ($sched->roomEvaluation) {
                $roomTag = $sched->roomEvaluation->isValid ? 'info' : ($sched->roomEvaluation->hasUsableRoom() ? 'comment' : 'error');
                $this->line("   Ruangan : <{$roomTag}>{$sched->roomEvaluation->notes}</{$roomTag}>");
                if ($sched->roomEvaluation->suggestedAlternativeRoom) {
                    $alt = $sched->roomEvaluation->suggestedAlternativeRoom;
                    $this->line("   Alternatif Ruangan: <info>{$alt->roomName} (Kapasitas: {$alt->capacity})</info>");
                }
            }

            if (! empty($sched->warnings)) {
                foreach ($sched->warnings as $warn) {
                    $this->line("   <fg=yellow;options=bold>[PERINGATAN]</> <comment>{$warn}</comment>");
                }
            }

            $this->newLine();

            // Tabel Kandidat Valid
            if (! empty($sched->validCandidates)) {
                $this->info('   [✓] Kandidat Pengganti yang Valid:');
                $validRows = [];
                foreach ($sched->validCandidates as $rank => $candidate) {
                    $validRows[] = [
                        $rank + 1,
                        $candidate->instructorName,
                        $candidate->skillName.' ('.ucfirst((string) $candidate->skillLevel).')',
                        $candidate->score,
                        $candidate->otherClassesCountToday.' kelas',
                        implode('; ', $candidate->reasons),
                    ];
                }

                $this->table(
                    ['Peringkat', 'Instruktur', 'Kompetensi', 'Skor', 'Beban Hari Ini', 'Alasan / Penilaian'],
                    $validRows
                );
            } else {
                $this->warn('   [!] Belum ada kandidat valid yang memenuhi syarat untuk kelas ini.');
            }

            // Tabel Kandidat Tidak Memenuhi Syarat
            if (! empty($sched->disqualifiedCandidates)) {
                $this->line('   <fg=gray>[✗] Kandidat Tidak Memenuhi Syarat:</>');
                $disqualifiedRows = [];
                foreach ($sched->disqualifiedCandidates as $candidate) {
                    $disqualifiedRows[] = [
                        $candidate->instructorName,
                        $candidate->competencyMatched ? 'Cocok' : 'Tidak Cocok',
                        $candidate->hasScheduleConflict ? 'Bentrok' : 'Bebas',
                        $candidate->hasLeaveConflict ? 'Sedang Izin' : 'Bebas',
                        $candidate->disqualificationReason,
                    ];
                }

                $this->table(
                    ['Instruktur', 'Kompetensi', 'Jadwal', 'Status Izin', 'Alasan Didiskualifikasi'],
                    $disqualifiedRows
                );
            }

            $this->line("   Ringkasan: <comment>{$sched->summary}</comment>");
            $this->line('-----------------------------------------------------------');
        }

        $this->newLine();
        if ($result->allSchedulesResolved) {
            $this->info("STATUS AKHIR: SUKSES - {$result->summary}");
        } else {
            $this->warn("STATUS AKHIR: PERLU PERHATIAN - {$result->summary}");
        }

        return self::SUCCESS;
    }
}
