<?php

namespace App\Models;

use Database\Factories\InstructorAvailabilityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $instructor_id
 * @property string $day
 * @property string $start_time
 * @property string $end_time
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Instructor $instructor
 */
class InstructorAvailability extends Model
{
    /** @use HasFactory<InstructorAvailabilityFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'instructor_id',
        'day',
        'start_time',
        'end_time',
    ];

    /**
     * Relasi ke instruktur pemilik jadwal ketersediaan ini.
     *
     * @return BelongsTo<Instructor, $this>
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class, 'instructor_id');
    }
}
