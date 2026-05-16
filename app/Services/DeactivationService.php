<?php

namespace App\Services;

use App\Models\AIAnalysis;
use App\Models\Driver;
use App\Models\DriverScore;
use App\Models\IncidentMedia;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

class DeactivationService
{
    public function __construct(private DriverScoreService $driverScoreService) {}

    /**
     * @return array{is_active: bool, deactivated_at: mixed, deactivated_by: int|null}
     */
    public function attributesForStatus(string $status, string $activeStatus, ?User $actor = null): array
    {
        $isActive = $status === $activeStatus;

        return [
            'is_active' => $isActive,
            'deactivated_at' => $isActive ? null : now(),
            'deactivated_by' => $isActive ? null : $actor?->id,
        ];
    }

    public function deactivateUser(User $user, User $actor): void
    {
        DB::transaction(function () use ($actor, $user): void {
            $user->update([
                'status' => User::STATUS_INACTIVE,
                ...$this->attributesForStatus(User::STATUS_INACTIVE, User::STATUS_ACTIVE, $actor),
            ]);

            $driver = $user->driverProfile;

            if ($driver instanceof Driver) {
                $driver->update([
                    'status' => Driver::STATUS_INACTIVE,
                    ...$this->attributesForStatus(Driver::STATUS_INACTIVE, Driver::STATUS_ACTIVE, $actor),
                ]);
            }
        });
    }

    public function reactivateUser(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->update([
                'status' => User::STATUS_ACTIVE,
                ...$this->attributesForStatus(User::STATUS_ACTIVE, User::STATUS_ACTIVE),
            ]);

            $driver = $user->driverProfile;

            if ($driver instanceof Driver) {
                $driver->update([
                    'status' => Driver::STATUS_ACTIVE,
                    ...$this->attributesForStatus(Driver::STATUS_ACTIVE, Driver::STATUS_ACTIVE),
                ]);
            }
        });
    }

    public function deactivateDriver(Driver $driver, User $actor): void
    {
        DB::transaction(function () use ($actor, $driver): void {
            $driver->update([
                'status' => Driver::STATUS_INACTIVE,
                ...$this->attributesForStatus(Driver::STATUS_INACTIVE, Driver::STATUS_ACTIVE, $actor),
            ]);

            $user = $driver->user;

            if ($user instanceof User) {
                $user->update([
                    'status' => User::STATUS_INACTIVE,
                    ...$this->attributesForStatus(User::STATUS_INACTIVE, User::STATUS_ACTIVE, $actor),
                ]);
            }
        });
    }

    public function reactivateDriver(Driver $driver): void
    {
        DB::transaction(function () use ($driver): void {
            $driver->update([
                'status' => Driver::STATUS_ACTIVE,
                ...$this->attributesForStatus(Driver::STATUS_ACTIVE, Driver::STATUS_ACTIVE),
            ]);

            $user = $driver->user;

            if ($user instanceof User) {
                $user->update([
                    'status' => User::STATUS_ACTIVE,
                    ...$this->attributesForStatus(User::STATUS_ACTIVE, User::STATUS_ACTIVE),
                ]);
            }
        });
    }

    public function deactivateVehicle(Vehicle $vehicle, User $actor): void
    {
        $vehicle->update([
            'status' => Vehicle::STATUS_RETIRED,
            ...$this->attributesForStatus(Vehicle::STATUS_RETIRED, Vehicle::STATUS_ACTIVE, $actor),
        ]);
    }

    public function reactivateVehicle(Vehicle $vehicle): void
    {
        $vehicle->update([
            'status' => Vehicle::STATUS_ACTIVE,
            ...$this->attributesForStatus(Vehicle::STATUS_ACTIVE, Vehicle::STATUS_ACTIVE),
        ]);
    }

    public function deactivateIncidentMedia(IncidentMedia $incidentMedia, User $actor): void
    {
        $incidentMedia->update([
            'is_active' => false,
            'deactivated_at' => now(),
            'deactivated_by' => $actor->id,
        ]);
    }

    public function reactivateIncidentMedia(IncidentMedia $incidentMedia): void
    {
        $incidentMedia->update([
            'is_active' => true,
            'deactivated_at' => null,
            'deactivated_by' => null,
        ]);
    }

    public function deactivateAIAnalysis(AIAnalysis $aiAnalysis, User $actor): void
    {
        $aiAnalysis->update([
            'status' => AIAnalysis::STATUS_INACTIVE,
            'is_active' => false,
            'deactivated_at' => now(),
            'deactivated_by' => $actor->id,
        ]);
    }

    public function reactivateAIAnalysis(AIAnalysis $aiAnalysis): void
    {
        $hasOutput = $aiAnalysis->summary !== null
            || $aiAnalysis->detected_events !== null
            || $aiAnalysis->confidence_score !== null
            || $aiAnalysis->recommendation !== null
            || $aiAnalysis->raw_response !== null;

        $aiAnalysis->update([
            'status' => $hasOutput ? AIAnalysis::STATUS_COMPLETED : AIAnalysis::STATUS_PENDING,
            'is_active' => true,
            'deactivated_at' => null,
            'deactivated_by' => null,
        ]);
    }

    public function deactivateDriverScore(DriverScore $driverScore, User $actor): void
    {
        $driverScore->update([
            'is_active' => false,
            'deactivated_at' => now(),
            'deactivated_by' => $actor->id,
        ]);
    }

    public function reactivateDriverScore(DriverScore $driverScore): void
    {
        $driverScore->update([
            'is_active' => true,
            'deactivated_at' => null,
            'deactivated_by' => null,
        ]);

        $driver = $driverScore->driver;

        if ($driver instanceof Driver) {
            $this->driverScoreService->recalculateForDriver($driver);
        }
    }
}
