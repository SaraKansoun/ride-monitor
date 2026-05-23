<?php

namespace App\Models;

use Database\Factories\AIAnalysisFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIAnalysis extends Model
{
    /** @use HasFactory<AIAnalysisFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_AI_ANALYZING = 'ai_analyzing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_AI_ANALYZING,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
        self::STATUS_INACTIVE,
    ];

    public const TERMINAL_STATUSES = [
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
        self::STATUS_INACTIVE,
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ai_analyses';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'incident_id',
        'media_fingerprint',
        'summary',
        'detected_events',
        'confidence_score',
        'recommendation',
        'suggested_fault_decision',
        'fault_confidence_score',
        'fault_reasoning',
        'raw_response',
        'status',
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
        'status' => self::STATUS_PROCESSING,
        'is_active' => true,
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function deactivator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    /**
     * @param  Builder<AIAnalysis>  $query
     * @return Builder<AIAnalysis>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<AIAnalysis>  $query
     * @return Builder<AIAnalysis>
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confidence_score' => 'float',
            'fault_confidence_score' => 'float',
            'deactivated_at' => 'datetime',
            'is_active' => 'boolean',
            'raw_response' => 'array',
        ];
    }
}
