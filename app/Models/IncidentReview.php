<?php

namespace App\Models;

use Database\Factories\IncidentReviewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentReview extends Model
{
    /** @use HasFactory<IncidentReviewFactory> */
    use HasFactory;

    public const FAULT_DRIVER = 'driver_fault';

    public const FAULT_OTHER_PARTY = 'other_party_fault';

    public const FAULT_SHARED = 'shared_fault';

    public const FAULT_UNCLEAR = 'unclear';

    public const FAULT_DECISIONS = [
        self::FAULT_DRIVER,
        self::FAULT_OTHER_PARTY,
        self::FAULT_SHARED,
        self::FAULT_UNCLEAR,
    ];

    public static function faultDecisionLabel(?string $faultDecision): string
    {
        return match ($faultDecision) {
            self::FAULT_DRIVER => 'Driver fault',
            self::FAULT_OTHER_PARTY => 'Other party fault',
            self::FAULT_SHARED => 'Shared fault',
            self::FAULT_UNCLEAR => 'Unclear',
            default => 'Pending',
        };
    }

    public static function faultDecisionLabelWithScore(?string $faultDecision, float|int|string|null $score): string
    {
        $label = self::faultDecisionLabel($faultDecision);

        if ($label === 'Pending' || ! is_numeric($score)) {
            return $label;
        }

        return sprintf('%s (%s)', $label, number_format((float) $score, 2));
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'incident_id',
        'reviewed_by',
        'fault_decision',
        'notes',
        'reviewed_at',
        'is_active',
        'deactivated_at',
        'deactivated_by',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function deactivator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    /**
     * @param  Builder<IncidentReview>  $query
     * @return Builder<IncidentReview>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<IncidentReview>  $query
     * @return Builder<IncidentReview>
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }
}
