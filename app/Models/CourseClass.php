<?php

namespace App\Models;

use Database\Factories\CourseClassFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'student_count' => 'integer',
        ];
    }

    /**
     * Schedules assigned to this course class.
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }
}
