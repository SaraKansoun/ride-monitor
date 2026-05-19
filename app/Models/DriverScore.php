<?php

namespace App\Models;

use Database\Factories\DriverScoreFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverScore extends Model
{
    /** @use HasFactory<DriverScoreFactory> */
    use HasFactory;

    public const DEFAULT_SCORE = 100;

    public const MIN_SCORE = 0;

    public const MAX_SCORE = 100;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'driver_id',
        'score',
        'total_incidents',
        'unsafe_events',
        'last_updated_at',
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
        'score' => self::DEFAULT_SCORE,
        'total_incidents' => 0,
        'unsafe_events' => 0,
        'is_active' => true,
    ];

    /**
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deactivator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    /**
     * @param  Builder<DriverScore>  $query
     * @return Builder<DriverScore>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<DriverScore>  $query
     * @return Builder<DriverScore>
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
            'deactivated_at' => 'datetime',
            'is_active' => 'boolean',
            'last_updated_at' => 'datetime',
            'score' => 'integer',
            'total_incidents' => 'integer',
            'unsafe_events' => 'integer',
        ];
    }
}
