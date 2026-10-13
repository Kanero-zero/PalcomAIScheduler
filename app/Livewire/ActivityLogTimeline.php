<?php

namespace App\Livewire;

use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class ActivityLogTimeline extends Component
{
    public string $type = 'all';

    public string $state = 'all';

    private const TYPES = ['all', 'leave', 'engine', 'ai', 'approval', 'schedule'];

    private const STATES = ['all', 'success', 'partial', 'fallback', 'not_applicable', 'pending', 'rejected', 'info', 'warning'];

    public function resetFilters(): void
    {
        $this->reset('type', 'state');
    }

    public function render(): View
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        // PR #16 belum merged: jangan query model/table yang belum ada.
        $backendReady = class_exists(ActivityLogService::class)
            && Schema::hasTable('activity_logs');

        $activities = [];
        if ($backendReady) {
            $type = in_array($this->type, self::TYPES, true) ? $this->type : 'all';
            $state = in_array($this->state, self::STATES, true) ? $this->state : 'all';

            $rows = app(ActivityLogService::class)->getTimelineActivities(
                type: $type === 'all' ? null : $type,
                state: $state === 'all' ? null : $state,
                limit: 50,
            );

            // Whitelist: jangan kirim metadata/alasan pribadi ke browser.
            $activities = array_map(static function (array $row): array {
                $type = (string) ($row['type'] ?? '');
                $state = (string) ($row['state'] ?? 'info');

                // Tampilkan status partial dari kontrak AI tanpa mengekspos metadata.
                if ($type === 'ai' && ($row['metadata']['status'] ?? '') === 'partial') {
                    $state = 'partial';
                }

                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'at' => (string) ($row['at'] ?? '-'),
                    'type' => $type,
                    'type_label' => (string) ($row['type_label'] ?? 'Aktivitas'),
                    'state' => $state,
                    'state_label' => $state === 'partial'
                        ? 'Sebagian Selesai'
                        : (string) ($row['state_label'] ?? 'Informasi'),
                    'actor' => (string) ($row['actor'] ?? 'Sistem'),
                    'title' => (string) ($row['title'] ?? 'Aktivitas sistem'),
                    'subject' => (string) ($row['subject'] ?? '-'),
                    // Narasi aman: alasan izin, catatan medis, dan rejection_reason
                    // dari backend tidak boleh ditampilkan sebelum audit privasi lulus.
                    'description' => match ($type) {
                        'leave' => 'Pengajuan izin instruktur telah dicatat.',
                        'engine' => 'Pemeriksaan kompetensi, jadwal, dan ruangan telah dicatat.',
                        'ai' => 'Hasil analisis AI atau fallback telah dicatat.',
                        'approval' => $state === 'rejected'
                            ? 'Usulan penggantian instruktur ditolak.'
                            : 'Keputusan persetujuan instruktur pengganti telah dicatat.',
                        'schedule' => 'Pembaruan jadwal kelas telah dicatat.',
                        default => 'Aktivitas aplikasi telah dicatat.',
                    },
                ];
            }, $rows);
        }

        return view('livewire.activity-log-timeline', [
            'backendReady' => $backendReady,
            'activities' => $activities,
        ]);
    }
}
