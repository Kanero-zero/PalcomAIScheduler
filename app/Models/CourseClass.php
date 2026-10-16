<?php

namespace App\Models;

use Database\Factories\CourseClassFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $subject
 * @property int $student_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CourseClass extends Model
{
    /** @use HasFactory<CourseClassFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'subject',
        'student_count',
        'program_id',
        'department',
        'kind',
        'start_date',
        'end_date',
        'total_meetings',
        'session_duration_minutes',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'student_count' => 'integer',
            'program_id' => 'integer',
            'total_meetings' => 'integer',
            'session_duration_minutes' => 'integer',
        ];
    }

    /**
     * Schedules assigned to this course class.
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    /**
     * Program studi yang menaungi kelas ini.
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * Snapshot kurikulum beku (immutable) saat kelas dibuka.
     */
    public function curriculumSnapshot(): HasOne
    {
        return $this->hasOne(ClassCurriculumSnapshot::class);
    }

    /**
     * Pola perulangan jadwal reguler untuk kelas ini (relasi jamak).
     */
    public function recurringPatterns(): HasMany
    {
        return $this->hasMany(RecurringPattern::class);
    }

    /**
     * Pola perulangan jadwal reguler tunggal (relasi utama).
     */
    public function recurringPattern(): HasOne
    {
        return $this->hasOne(RecurringPattern::class);
    }

    /**
     * Proposal-proposal penjadwalan yang diajukan untuk kelas ini.
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(ScheduleProposal::class);
    }
}
