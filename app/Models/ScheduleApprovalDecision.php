<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $proposal_id
 * @property int $round
 * @property int $decision_by
 * @property string $decision
 * @property string|null $reason
 * @property string $idempotency_token
 * @property Carbon $decided_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ScheduleApprovalDecision extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'proposal_id',
        'round',
        'decision_by',
        'decision',
        'reason',
        'idempotency_token',
        'decided_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'round' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * Proposal yang dievaluasi dalam keputusan ini.
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(ScheduleProposal::class, 'proposal_id');
    }

    /**
     * Pejabat BAAK yang menetapkan keputusan.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decision_by');
    }

    /**
     * Helper cek keputusan disetujui.
     */
    public function isApproved(): bool
    {
        return $this->decision === 'approved';
    }

    /**
     * Helper cek keputusan ditolak.
     */
    public function isRejected(): bool
    {
        return $this->decision === 'rejected';
    }

    /**
     * Helper cek keputusan butuh revisi.
     */
    public function needsRevision(): bool
    {
        return $this->decision === 'needs_revision';
    }
}
