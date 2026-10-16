<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $course_class_id
 * @property int|null $source_curriculum_version_id
 * @property array $snapshot_payload
 * @property Carbon $captured_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ClassCurriculumSnapshot extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'course_class_id',
        'source_curriculum_version_id',
        'snapshot_payload',
        'captured_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot_payload' => 'array',
            'captured_at' => 'datetime',
        ];
    }

    /**
     * Kelas pemilik snapshot kurikulum ini.
     */
    public function courseClass(): BelongsTo
    {
        return $this->belongsTo(CourseClass::class);
    }

    /**
     * Versi kurikulum asal pembuatan snapshot (template rujukan awal).
     */
    public function sourceCurriculumVersion(): BelongsTo
    {
        return $this->belongsTo(CurriculumVersion::class, 'source_curriculum_version_id');
    }
}
