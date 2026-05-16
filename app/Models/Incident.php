<?php

namespace App\Models;

use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use HasFactory;

    public const TYPE_CRASH = 'crash';

    public const TYPE_UNSAFE_DRIVING = 'unsafe_driving';

    public const TYPE_COMPLAINT = 'complaint';

    public const TYPE_NEAR_MISS = 'near_miss';

    public const TYPES = [
        self::TYPE_CRASH,
        self::TYPE_UNSAFE_DRIVING,
        self::TYPE_COMPLAINT,
        self::TYPE_NEAR_MISS,
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_RESOLVED,
        self::STATUS_INACTIVE,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'driver_id',
        'vehicle_id',
        'type',
        'description',
        'status',
        'reported_by',
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
        'status' => self::STATUS_PENDING,
        'is_active' => true,
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function deactivator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    public function media(): HasMany
    {
        return $this->hasMany(IncidentMedia::class);
    }

    public function aiAnalyses(): HasMany
    {
        return $this->hasMany(AIAnalysis::class);
    }

    public function activeAiAnalysis(): HasOne
    {
        return $this->hasOne(AIAnalysis::class)->where('is_active', true)->latestOfMany();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(IncidentReview::class);
    }

    public function activeReview(): HasOne
    {
        return $this->hasOne(IncidentReview::class)->where('is_active', true)->latestOfMany('reviewed_at');
    }

    /**
     * @param  Builder<Incident>  $query
     * @return Builder<Incident>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<Incident>  $query
     * @return Builder<Incident>
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
        ];
    }
}
