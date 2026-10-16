<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $course_class_id
 * @property int $frequency_per_week
 * @property array $preferred_weekdays
 * @property string $start_time
 * @property string $end_time
 * @property int $duration_minutes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RecurringPattern extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'course_class_id',
        'frequency_per_week',
        'preferred_weekdays',
        'start_time',
        'end_time',
        'duration_minutes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'frequency_per_week' => 'integer',
            'preferred_weekdays' => 'array',
            'duration_minutes' => 'integer',
        ];
    }

    /**
     * Kelas pemilik pola perulangan jadwal ini.
     */
    public function courseClass(): BelongsTo
    {
        return $this->belongsTo(CourseClass::class);
    }
}
