<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $program_id
 * @property string $version_code
 * @property string $status
 * @property Carbon|null $published_at
 * @property int|null $published_by
 * @property int|null $parent_version_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CurriculumVersion extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'program_id',
        'version_code',
        'status',
        'published_at',
        'published_by',
        'parent_version_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    /**
     * Scope untuk versi kurikulum yang telah dipublikasikan secara resmi.
     *
     * @param  Builder<self>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published');
    }

    /**
     * Scope versi draf.
     *
     * @param  Builder<self>  $query
     */
    public function scopeDraft(Builder $query): void
    {
        $query->where('status', 'draft');
    }

    /**
     * Program studi pemilik kurikulum ini.
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * Pengguna/BAAK yang mengesahkan versi kurikulum ini.
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * Versi kurikulum induk rujukan sebelumnya jika ada.
     */
    public function parentVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_version_id');
    }

    /**
     * Versi-versi revisi turunan.
     */
    public function childVersions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_version_id');
    }

    /**
     * Periode/tahapan dalam kurikulum ini (diurutkan berdasarkan sort_order).
     */
    public function periods(): HasMany
    {
        return $this->hasMany(CurriculumPeriod::class)->orderBy('sort_order');
    }

    /**
     * Seluruh mata pelajaran dalam kurikulum ini melalui tahapan periodenya.
     */
    public function subjects(): HasManyThrough
    {
        return $this->hasManyThrough(CurriculumSubject::class, CurriculumPeriod::class);
    }

    /**
     * Snapshot kurikulum kelas yang merujuk pada versi ini.
     */
    public function classSnapshots(): HasMany
    {
        return $this->hasMany(ClassCurriculumSnapshot::class, 'source_curriculum_version_id');
    }
}
