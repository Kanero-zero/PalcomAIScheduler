<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $department
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Program extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
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
     * Scope program aktif.
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
        if ($department !== null && $department !== '') {
            $query->where('department', $department);
        }
    }

    /**
     * Seluruh versi kurikulum milik program ini.
     */
    public function curriculumVersions(): HasMany
    {
        return $this->hasMany(CurriculumVersion::class);
    }

    /**
     * Versi kurikulum resmi yang telah dipublikasikan (terbaru).
     */
    public function latestPublishedCurriculumVersion(): HasOne
    {
        return $this->hasOne(CurriculumVersion::class)
            ->where('status', 'published')
            ->latestOfMany('published_at');
    }

    /**
     * Kelas-kelas kursus yang bernaung di bawah program ini.
     */
    public function courseClasses(): HasMany
    {
        return $this->hasMany(CourseClass::class);
    }
}
