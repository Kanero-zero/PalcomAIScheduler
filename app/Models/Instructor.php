<?php

namespace App\Models;

use Database\Factories\InstructorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $department
 * @property string $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, InstructorSkill> $skills
 * @property-read Collection<int, InstructorAvailability> $availabilities
 * @property-read Collection<int, Schedule> $schedules
 * @property-read Collection<int, InstructorLeave> $leaves
 */
class Instructor extends Model
{
    /** @use HasFactory<InstructorFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'department',
        'status',
        'notes',
    ];

    /**
     * Skills mastered by this instructor.
     *
     * @return HasMany<InstructorSkill, $this>
     */
    public function skills(): HasMany
    {
        return $this->hasMany(InstructorSkill::class, 'instructor_id');
    }

    /**
     * Teaching availability slots for this instructor.
     *
     * @return HasMany<InstructorAvailability, $this>
     */
    public function availabilities(): HasMany
    {
        return $this->hasMany(InstructorAvailability::class, 'instructor_id');
    }

    /**
     * Teaching schedules assigned to this instructor.
     *
     * @return HasMany<Schedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    /**
     * Leave records for this instructor.
     *
     * @return HasMany<InstructorLeave, $this>
     */
    public function leaves(): HasMany
    {
        return $this->hasMany(InstructorLeave::class);
    }

    /**
     * Scope filter by department.
     *
     * @param  Builder<self>  $query
     */
    public function scopeByDepartment(Builder $query, ?string $department): void
    {
        if ($department) {
            $query->where('department', $department);
        }
    }
}
