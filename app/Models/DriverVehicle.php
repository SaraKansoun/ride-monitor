<?php

namespace App\Models;

use Database\Factories\DriverVehicleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverVehicle extends Model
{
    /** @use HasFactory<DriverVehicleFactory> */
    use HasFactory;

    protected $table = 'driver_vehicle';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'driver_id',
        'vehicle_id',
        'assigned_at',
        'unassigned_at',
    ];

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
     * @param  Builder<DriverVehicle>  $query
     * @return Builder<DriverVehicle>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('unassigned_at');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'unassigned_at' => 'datetime',
        ];
    }
}
