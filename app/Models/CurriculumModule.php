<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $curriculum_subject_id
 * @property string $title
 * @property int $target_completed_meetings
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CurriculumModule extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'curriculum_subject_id',
        'title',
        'target_completed_meetings',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_completed_meetings' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Mata pelajaran induk modul ini.
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(CurriculumSubject::class, 'curriculum_subject_id');
    }
}
