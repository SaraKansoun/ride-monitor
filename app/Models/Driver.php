<?php

namespace App\Models;

use Database\Factories\DriverFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Driver extends Model
{
    /** @use HasFactory<DriverFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_SUSPENDED,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'license_number',
        'phone',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deactivator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DriverVehicle::class);
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(DriverVehicle::class)->whereNull('unassigned_at')->latestOfMany('assigned_at');
    }

    public function currentAssignments(): HasMany
    {
        return $this->hasMany(DriverVehicle::class)->whereNull('unassigned_at')->latest('assigned_at');
    }

    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class, 'driver_vehicle')
            ->withPivot(['assigned_at', 'unassigned_at'])
            ->withTimestamps();
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function unresolvedActiveIncidents(): HasMany
    {
        return $this->hasMany(Incident::class)
            ->active()
            ->whereIn('status', [Incident::STATUS_PENDING, Incident::STATUS_UNDER_REVIEW]);
    }

    public function score(): HasOne
    {
        return $this->hasOne(DriverScore::class);
    }

    /**
     * @param  Builder<Driver>  $query
     * @return Builder<Driver>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('status', self::STATUS_ACTIVE);
    }

    /**
     * @param  Builder<Driver>  $query
     * @return Builder<Driver>
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

    public function hasUnresolvedActiveIncidents(): bool
    {
        return $this->unresolvedActiveIncidents()->exists();
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
