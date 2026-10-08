<?php

namespace App\Models;

use Database\Factories\InstructorLeaveFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
}
