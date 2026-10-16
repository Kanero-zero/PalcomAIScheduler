<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $proposal_id
 * @property string $option_label
 * @property array $proposed_schedules_payload
 * @property int $conflicts_count
 * @property int $score
 * @property string|null $rationale
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ScheduleProposalOption extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'proposal_id',
        'option_label',
        'proposed_schedules_payload',
        'conflicts_count',
        'score',
        'rationale',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'proposed_schedules_payload' => 'array',
            'conflicts_count' => 'integer',
            'score' => 'integer',
        ];
    }

    /**
     * Proposal induk yang menaungi opsi ini.
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(ScheduleProposal::class, 'proposal_id');
    }

    /**
     * Cek apakah opsi ini saat ini merupakan opsi terpilih pada proposal.
     */
    public function isSelected(): bool
    {
        return $this->proposal?->selected_option_id === $this->id;
    }
}
