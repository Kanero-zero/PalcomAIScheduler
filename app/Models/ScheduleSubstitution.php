<?php

namespace App\Models;

use Database\Factories\ScheduleSubstitutionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $instructor_leave_id
 * @property int $schedule_id
 * @property int $original_instructor_id
 * @property int|null $replacement_instructor_id
 * @property string $status
 * @property int|null $decision_by
 * @property Carbon|null $decision_at
 * @property string|null $rejection_reason
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InstructorLeave $leave
 * @property-read Schedule $schedule
 * @property-read Instructor $originalInstructor
 * @property-read Instructor|null $replacementInstructor
 * @property-read User|null $decisionMaker
 */
class ScheduleSubstitution extends Model
{
    /** @use HasFactory<ScheduleSubstitutionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'instructor_leave_id',
        'schedule_id',
        'original_instructor_id',
        'replacement_instructor_id',
        'status',
        'decision_by',
        'decision_at',
        'rejection_reason',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decision_at' => 'datetime',
        ];
    }

    /**
     * Pengajuan izin yang mendasari pergantian jadwal ini.
     */
    public function leave(): BelongsTo
    {
        return $this->belongsTo(InstructorLeave::class, 'instructor_leave_id');
    }

    /**
     * Jadwal kelas yang terdampak oleh izin instruktur.
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    /**
     * Instruktur asal yang mengajukan izin berhalangan hadir.
     */
    public function originalInstructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class, 'original_instructor_id');
    }

    /**
     * Instruktur pengganti yang disetujui untuk mengajar (null jika ditolak atau pending).
     */
    public function replacementInstructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class, 'replacement_instructor_id');
    }

    /**
     * Pengguna/Admin yang membuat keputusan persetujuan atau penolakan.
     */
    public function decisionMaker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decision_by');
    }

    /**
     * Memeriksa apakah keputusan telah disetujui.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Memeriksa apakah keputusan telah ditolak.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Memeriksa apakah keputusan masih pending (belum diputuskan).
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
