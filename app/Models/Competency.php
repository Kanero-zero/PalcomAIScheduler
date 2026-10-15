<?php

namespace App\Models;

use Database\Factories\CompetencyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $department
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Competency extends Model
{
    /** @use HasFactory<CompetencyFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'department',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope untuk kompetensi yang aktif saja.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Scope filter berdasarkan bidang pembelajaran ('computer' atau 'speakout').
     *
     * @param  Builder<self>  $query
     */
    public function scopeByDepartment(Builder $query, ?string $department): void
    {
        if ($department) {
            $query->where('department', $department);
        }
    }

    /**
     * Relasi ke pengajar yang menguasai kompetensi ini.
     *
     * @return HasMany<InstructorSkill, $this>
     */
    public function instructorSkills(): HasMany
    {
        return $this->hasMany(InstructorSkill::class, 'competency_id');
    }
}
