<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $curriculum_version_id
 * @property string $name
 * @property int $sort_order
 * @property int|null $duration_weeks
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CurriculumPeriod extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'curriculum_version_id',
        'name',
        'sort_order',
        'duration_weeks',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'duration_weeks' => 'integer',
        ];
    }

    /**
     * Versi kurikulum induk.
     */
    public function curriculumVersion(): BelongsTo
    {
        return $this->belongsTo(CurriculumVersion::class);
    }

    /**
     * Mata pelajaran pada periode kurikulum ini (diurutkan berdasarkan sort_order).
     */
    public function subjects(): HasMany
    {
        return $this->hasMany(CurriculumSubject::class)->orderBy('sort_order');
    }
}
