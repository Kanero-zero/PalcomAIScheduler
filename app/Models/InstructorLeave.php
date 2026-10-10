<?php

namespace App\Models;

use App\Services\ActivityLog\ActivityLogService;
use Database\Factories\InstructorLeaveFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $instructor_id
 * @property Carbon $date
 * @property string $start_time
 * @property string $end_time
 * @property string|null $reason
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class InstructorLeave extends Model
{
    /** @use HasFactory<InstructorLeaveFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'instructor_id',
        'date',
        'start_time',
        'end_time',
        'reason',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /**
     * The instructor who requested this leave.
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::created(function (InstructorLeave $leave) {
            app(ActivityLogService::class)->logLeaveCreated($leave);
        });
    }

    /**
     * Keputusan pergantian instruktur untuk jadwal-jadwal yang terdampak izin ini.
     *
     * @return HasMany<ScheduleSubstitution, $this>
     */
    public function substitutions(): HasMany
    {
        return $this->hasMany(ScheduleSubstitution::class, 'instructor_leave_id');
    }

    /**
     * Riwayat log aktivitas yang terkait dengan pengajuan izin ini.
     *
     * @return HasMany<ActivityLog, $this>
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'instructor_leave_id');
    }
}
