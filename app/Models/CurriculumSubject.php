<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $curriculum_period_id
 * @property int|null $competency_id
 * @property string $name
 * @property int $sessions_per_week
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CurriculumSubject extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'curriculum_period_id',
        'competency_id',
        'name',
        'sessions_per_week',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sessions_per_week' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Periode kurikulum induk.
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(CurriculumPeriod::class, 'curriculum_period_id');
    }

    /**
     * Kompetensi yang wajib dimiliki oleh instruktur untuk mengajar mata pelajaran ini.
     */
    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class);
    }

    /**
     * Modul-modul materi pembelajaran di bawah mata pelajaran ini (diurutkan).
     */
    public function modules(): HasMany
    {
        return $this->hasMany(CurriculumModule::class)->orderBy('sort_order');
    }

    /**
     * Jadwal-jadwal pertemuan yang menyelenggarakan mata pelajaran ini.
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }
}
