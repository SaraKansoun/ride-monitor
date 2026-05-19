<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_MAINTENANCE = 'maintenance';

    public const STATUS_RETIRED = 'retired';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_MAINTENANCE,
        self::STATUS_RETIRED,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'plate_number',
        'model',
        'year',
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
        'status' => self::STATUS_ACTIVE,
        'is_active' => true,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function deactivator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    /**
     * @return HasMany<DriverVehicle, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(DriverVehicle::class);
    }

    /**
     * @return HasOne<DriverVehicle, $this>
     */
    public function currentAssignment(): HasOne
    {
        return $this->hasOne(DriverVehicle::class)->whereNull('unassigned_at')->latestOfMany('assigned_at');
    }

    /**
     * @return BelongsToMany<Driver, $this>
     */
    public function drivers(): BelongsToMany
    {
        return $this->belongsToMany(Driver::class, 'driver_vehicle')
            ->withPivot(['assigned_at', 'unassigned_at'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<Incident, $this>
     */
    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    /**
     * @param  Builder<Vehicle>  $query
     * @return Builder<Vehicle>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('status', self::STATUS_ACTIVE);
    }

    /**
     * @param  Builder<Vehicle>  $query
     * @return Builder<Vehicle>
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query
                ->where('is_active', false)
                ->orWhere('status', '!=', self::STATUS_ACTIVE);
        });
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && (bool) $this->getAttribute('is_active');
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
        ];
    }
}
