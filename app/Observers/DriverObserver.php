<?php

namespace App\Observers;

use App\Models\Driver;
use App\Services\DriverScoreService;

class DriverObserver
{
    public function __construct(private DriverScoreService $driverScoreService) {}

    /**
     * Handle the Driver "created" event.
     */
    public function created(Driver $driver): void
    {
        $this->driverScoreService->ensureDefaultScore($driver);
    }
}
