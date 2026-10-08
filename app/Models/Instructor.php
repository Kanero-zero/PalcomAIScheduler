<?php

namespace App\Models;

use Database\Factories\InstructorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
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
        'status',
    ];

    /**
     * Skills mastered by this instructor.
     */
    public function skills(): HasMany
    {
        return $this->hasMany(InstructorSkill::class, 'instructor_id');
    }

    /**
     * Teaching schedules assigned to this instructor.
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    /**
     * Leave records for this instructor.
     */
    public function leaves(): HasMany
    {
        return $this->hasMany(InstructorLeave::class);
    }
}
