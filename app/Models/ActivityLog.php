<?php

namespace App\Models;

use Database\Factories\ActivityLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $type
 * @property string $state
 * @property string $actor
 * @property string $title
 * @property string $description
 * @property string $subject
 * @property int|null $instructor_leave_id
 * @property int|null $schedule_id
 * @property string|null $fingerprint
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read InstructorLeave|null $instructorLeave
 * @property-read Schedule|null $schedule
 * @property-read string $type_label
 * @property-read string $state_label
 */
class ActivityLog extends Model
{
    /** @use HasFactory<ActivityLogFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'state',
        'actor',
        'title',
        'description',
        'subject',
        'instructor_leave_id',
        'schedule_id',
        'fingerprint',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /**
     * Pengguna/admin yang melakukan tindakan (jika dilakukan aktor manusia).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Pengajuan izin yang terkait dengan aktivitas.
     */
    public function instructorLeave(): BelongsTo
    {
        return $this->belongsTo(InstructorLeave::class);
    }

    /**
     * Jadwal kelas yang terkait dengan aktivitas.
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /**
     * Label representatif untuk jenis aktivitas di UI.
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'leave' => 'Pengajuan Izin',
            'engine' => 'Scheduling Engine',
            'ai' => 'Analisis AI',
            'approval' => 'Persetujuan',
            'schedule' => 'Jadwal',
            default => ucfirst($this->type),
        };
    }

    /**
     * Label representatif untuk status aktivitas di UI.
     */
    public function getStateLabelAttribute(): string
    {
        return match ($this->state) {
            'success' => match ($this->type) {
                'approval' => 'Disetujui',
                'schedule' => 'Berhasil',
                default => 'Selesai',
            },
            'fallback' => 'Mode Fallback',
            'not_applicable' => 'Tidak Diperlukan',
            'pending' => 'Menunggu',
            'rejected' => 'Ditolak',
            'info' => 'Informasi',
            'warning' => 'Peringatan',
            default => ucfirst($this->state),
        };
    }

    /**
     * Format data terstruktur untuk konsumsi Livewire Timeline UI.
     *
     * @return array<string, mixed>
     */
    public function toTimelineArray(): array
    {
        $at = $this->created_at ? $this->created_at->translatedFormat('d M Y, H.i').' WIB' : '-';

        return [
            'id' => $this->id,
            'at' => $at,
            'type' => $this->type,
            'type_label' => $this->type_label,
            'state' => $this->state,
            'state_label' => $this->state_label,
            'actor' => $this->actor,
            'title' => $this->title,
            'description' => $this->description,
            'subject' => $this->subject,
            'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Scope untuk mengurutkan timeline aktivitas terbaru.
     *
     * @param  Builder<self>  $query
     */
    public function scopeForTimeline(Builder $query): void
    {
        $query->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * Scope untuk filter jenis aktivitas.
     *
     * @param  Builder<self>  $query
     */
    public function scopeByType(Builder $query, ?string $type): void
    {
        if ($type && $type !== 'all') {
            $query->where('type', $type);
        }
    }

    /**
     * Scope untuk filter status aktivitas.
     *
     * @param  Builder<self>  $query
     */
    public function scopeByState(Builder $query, ?string $state): void
    {
        if ($state && $state !== 'all') {
            $query->where('state', $state);
        }
    }
}
