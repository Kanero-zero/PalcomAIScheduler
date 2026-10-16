<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * @property int $id
 * @property int|null $course_class_id
 * @property int $requested_by
 * @property string $title
 * @property string $input_mode
 * @property int|null $program_id
 * @property int|null $curriculum_version_id
 * @property string $department
 * @property string $class_kind
 * @property int $student_count
 * @property string $start_date
 * @property string $end_date
 * @property int $total_meetings
 * @property int $session_duration_minutes
 * @property int $frequency_per_week
 * @property array|null $preferred_weekdays
 * @property array|null $preferred_time_window
 * @property int|null $preferred_instructor_id
 * @property int|null $preferred_room_id
 * @property array|null $required_competency_ids
 * @property array|null $required_facility_codes
 * @property string|null $ai_prompt
 * @property int|null $selected_option_id
 * @property string $status
 * @property Carbon|null $submitted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ScheduleProposal extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'course_class_id',
        'requested_by',
        'title',
        'input_mode',
        'program_id',
        'curriculum_version_id',
        'department',
        'class_kind',
        'student_count',
        'start_date',
        'end_date',
        'total_meetings',
        'session_duration_minutes',
        'frequency_per_week',
        'preferred_weekdays',
        'preferred_time_window',
        'preferred_instructor_id',
        'preferred_room_id',
        'required_competency_ids',
        'required_facility_codes',
        'ai_prompt',
        'selected_option_id',
        'status',
        'submitted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'student_count' => 'integer',
            'total_meetings' => 'integer',
            'session_duration_minutes' => 'integer',
            'frequency_per_week' => 'integer',
            'preferred_weekdays' => 'array',
            'preferred_time_window' => 'array',
            'required_competency_ids' => 'array',
            'required_facility_codes' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * Boot the model to enforce integrity rules across all saving operations.
     */
    protected static function booted(): void
    {
        static::saving(function (self $proposal): void {
            if ($proposal->isDirty('selected_option_id') && $proposal->selected_option_id !== null) {
                $option = ScheduleProposalOption::find($proposal->selected_option_id);
                if (! $option || $option->proposal_id !== $proposal->id) {
                    throw new InvalidArgumentException(
                        "Opsi #{$proposal->selected_option_id} bukan bagian dari proposal #{$proposal->id}. Integritas relasi proposal-opsi dilanggar."
                    );
                }
            }
        });
    }

    /**
     * Scope proposal berstatus pending (menunggu review BAAK).
     *
     * @param  Builder<self>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', 'pending');
    }

    /**
     * Scope filter bidang pembelajaran.
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
     * Kelas kursus target (jika ditautkan ke kelas eksisting).
     */
    public function courseClass(): BelongsTo
    {
        return $this->belongsTo(CourseClass::class);
    }

    /**
     * Pengguna pengaju proposal (AO).
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Program studi target.
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * Versi kurikulum acuan.
     */
    public function curriculumVersion(): BelongsTo
    {
        return $this->belongsTo(CurriculumVersion::class);
    }

    /**
     * Instruktur yang diminta (pada mode Input Manual).
     */
    public function preferredInstructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class, 'preferred_instructor_id');
    }

    /**
     * Ruangan yang diminta (pada mode Input Manual).
     */
    public function preferredRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'preferred_room_id');
    }

    /**
     * Seluruh alternatif opsi jadwal (A, B, C) untuk proposal ini.
     */
    public function options(): HasMany
    {
        return $this->hasMany(ScheduleProposalOption::class, 'proposal_id');
    }

    /**
     * Opsi jadwal yang dipilih untuk diajukan ke BAAK.
     * Constraint integritas: Hanya dapat merujuk pada opsi milik proposal ini.
     */
    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(ScheduleProposalOption::class, 'selected_option_id')
            ->where('proposal_id', $this->id);
    }

    /**
     * Seluruh riwayat putaran keputusan persetujuan BAAK untuk proposal ini.
     */
    public function decisions(): HasMany
    {
        return $this->hasMany(ScheduleApprovalDecision::class, 'proposal_id')->orderBy('round');
    }

    /**
     * Alias relasi decisions untuk kompatibilitas penamaan approvalDecisions.
     *
     * @return HasMany<ScheduleApprovalDecision, $this>
     */
    public function approvalDecisions(): HasMany
    {
        return $this->decisions();
    }

    /**
     * Keputusan BAAK paling mutakhir (putaran terakhir).
     */
    public function latestDecision(): HasOne
    {
        return $this->hasOne(ScheduleApprovalDecision::class, 'proposal_id')->latestOfMany('decided_at');
    }

    /**
     * Menetapkan opsi terpilih secara aman dengan validasi kepemilikan proposal.
     *
     * @throws InvalidArgumentException Jika opsi bukan milik proposal ini.
     */
    public function selectOption(ScheduleProposalOption $option): void
    {
        if ($option->proposal_id !== $this->id) {
            throw new InvalidArgumentException("Opsi #{$option->id} (Label {$option->option_label}) bukan bagian dari proposal #{$this->id}. Penetapan ditolak.");
        }

        $this->update(['selected_option_id' => $option->id]);
    }
}
