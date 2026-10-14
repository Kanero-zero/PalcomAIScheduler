<?php

namespace App\Models;

use Database\Factories\InstructorSkillFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $instructor_id
 * @property int|null $competency_id
 * @property string $skill
 * @property string $level
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Instructor $instructor
 * @property-read Competency|null $competency
 */
class InstructorSkill extends Model
{
    /** @use HasFactory<InstructorSkillFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'instructor_skills';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'instructor_id',
        'competency_id',
        'skill',
        'level',
    ];

    /**
     * The instructor that owns this skill.
     *
     * @return BelongsTo<Instructor, $this>
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class, 'instructor_id');
    }

    /**
     * The master competency record if linked.
     *
     * @return BelongsTo<Competency, $this>
     */
    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class, 'competency_id');
    }
}
