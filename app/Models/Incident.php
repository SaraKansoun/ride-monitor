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

    public const SEVERITY_LOW = 'low';

    public const SEVERITY_MEDIUM = 'medium';

    public const SEVERITY_HIGH = 'high';

    public const SEVERITY_CRITICAL = 'critical';

    public const SEVERITIES = [
        self::SEVERITY_LOW,
        self::SEVERITY_MEDIUM,
        self::SEVERITY_HIGH,
        self::SEVERITY_CRITICAL,
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
        'severity',
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

    public static function severityForType(string $type): string
    {
        return match ($type) {
            self::TYPE_CRASH => self::SEVERITY_HIGH,
            self::TYPE_COMPLAINT => self::SEVERITY_LOW,
            self::TYPE_NEAR_MISS, self::TYPE_UNSAFE_DRIVING => self::SEVERITY_MEDIUM,
            default => self::SEVERITY_MEDIUM,
        };
    }

    /**
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deactivator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    /**
     * @return HasMany<IncidentMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(IncidentMedia::class);
    }

    /**
     * @return HasMany<AIAnalysis, $this>
     */
    public function aiAnalyses(): HasMany
    {
        return $this->hasMany(AIAnalysis::class);
    }

    /**
     * @return HasOne<AIAnalysis, $this>
     */
    public function activeAiAnalysis(): HasOne
    {
        return $this->hasOne(AIAnalysis::class)->where('is_active', true)->latestOfMany();
    }

    /**
     * @return HasMany<IncidentReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(IncidentReview::class);
    }

    /**
     * @return HasOne<IncidentReview, $this>
     */
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

    protected static function booted(): void
    {
        static::creating(function (Incident $incident): void {
            if (blank($incident->severity)) {
                $incident->severity = self::severityForType((string) $incident->type);
            }
        });
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
