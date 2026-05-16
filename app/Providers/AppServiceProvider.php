<?php

namespace App\Providers;

use App\Models\AIAnalysis;
use App\Models\Driver;
use App\Models\DriverScore;
use App\Models\Incident;
use App\Models\IncidentMedia;
use App\Models\IncidentReview;
use App\Models\User;
use App\Models\Vehicle;
use App\Observers\DriverObserver;
use App\Policies\AIAnalysisPolicy;
use App\Policies\DriverPolicy;
use App\Policies\DriverScorePolicy;
use App\Policies\IncidentMediaPolicy;
use App\Policies\IncidentPolicy;
use App\Policies\IncidentReviewPolicy;
use App\Policies\UserPolicy;
use App\Policies\VehiclePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Driver::observe(DriverObserver::class);

        Gate::policy(AIAnalysis::class, AIAnalysisPolicy::class);
        Gate::policy(Driver::class, DriverPolicy::class);
        Gate::policy(DriverScore::class, DriverScorePolicy::class);
        Gate::policy(Incident::class, IncidentPolicy::class);
        Gate::policy(IncidentMedia::class, IncidentMediaPolicy::class);
        Gate::policy(IncidentReview::class, IncidentReviewPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Vehicle::class, VehiclePolicy::class);
    }
}
