<?php

namespace App\Models;

use Database\Factories\ScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $course_class_id
 * @property int $instructor_id
 * @property int $room_id
 * @property Carbon $date
 * @property string $start_time
 * @property string $end_time
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Schedule extends Model
{
    /** @use HasFactory<ScheduleFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'course_class_id',
        'instructor_id',
        'room_id',
        'date',
        'start_time',
        'end_time',
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
     * The class associated with this schedule.
     */
    public function courseClass(): BelongsTo
    {
        return $this->belongsTo(CourseClass::class);
    }

    /**
     * The instructor teaching this schedule.
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    /**
     * The room where this schedule takes place.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Riwayat atau keputusan pergantian instruktur untuk jadwal ini.
     *
     * @return HasMany<ScheduleSubstitution, $this>
     */
    public function substitutions(): HasMany
    {
        return $this->hasMany(ScheduleSubstitution::class, 'schedule_id');
    }
}
