<?php

namespace Database\Seeders;

use App\Models\AIAnalysis;
use App\Models\Driver;
use App\Models\DriverVehicle;
use App\Models\Incident;
use App\Models\IncidentMedia;
use App\Models\IncidentReview;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DriverScoreService;
use App\Services\PermissionCatalog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(DriverScoreService $driverScoreService): void
    {
        DB::transaction(function () use ($driverScoreService): void {
            $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
            $monitor = User::query()->where('email', 'monitor@example.com')->firstOrFail();

            $drivers = $this->seedDrivers($admin);
            $vehicles = $this->seedVehicles($admin);

            $this->seedAssignments($drivers, $vehicles);
            $incidents = $this->seedIncidents($drivers, $vehicles, $monitor, $admin);

            $this->seedAnalyses($incidents);
            $this->seedReviews($incidents, $monitor);
            $this->seedMedia($incidents, $monitor);

            $drivers->each(fn (Driver $driver) => $driverScoreService->recalculateForDriver($driver));
        });
    }

    /**
     * @return Collection<int, Driver>
     */
    private function seedDrivers(User $admin): Collection
    {
        $drivers = collect([
            [
                'name' => 'Layla Haddad',
                'email' => 'layla.driver@example.com',
                'license_number' => 'DEMO-LAYLA-001',
                'phone' => '+961 70 100 101',
                'status' => Driver::STATUS_ACTIVE,
            ],
            [
                'name' => 'Karim Mansour',
                'email' => 'karim.driver@example.com',
                'license_number' => 'DEMO-KARIM-002',
                'phone' => '+961 70 100 102',
                'status' => Driver::STATUS_ACTIVE,
            ],
            [
                'name' => 'Nour Saliba',
                'email' => 'nour.driver@example.com',
                'license_number' => 'DEMO-NOUR-003',
                'phone' => '+961 70 100 103',
                'status' => Driver::STATUS_ACTIVE,
            ],
            [
                'name' => 'Samir Khoury',
                'email' => 'samir.driver@example.com',
                'license_number' => 'DEMO-SAMIR-004',
                'phone' => '+961 70 100 104',
                'status' => Driver::STATUS_ACTIVE,
            ],
            [
                'name' => 'Rana Nassar',
                'email' => 'rana.driver@example.com',
                'license_number' => 'DEMO-RANA-005',
                'phone' => '+961 70 100 105',
                'status' => Driver::STATUS_ACTIVE,
            ],
            [
                'name' => 'Omar Farah',
                'email' => 'omar.driver@example.com',
                'license_number' => 'DEMO-OMAR-006',
                'phone' => '+961 70 100 106',
                'status' => Driver::STATUS_INACTIVE,
                'deactivated_at' => now()->subDays(18),
            ],
        ])->map(function (array $driverData) use ($admin): Driver {
            $isActive = $driverData['status'] === Driver::STATUS_ACTIVE;

            $user = User::query()->updateOrCreate(
                ['email' => $driverData['email']],
                [
                    'name' => $driverData['name'],
                    'password' => 'password',
                    'status' => $isActive ? User::STATUS_ACTIVE : User::STATUS_INACTIVE,
                    'is_active' => $isActive,
                    'deactivated_at' => $driverData['deactivated_at'] ?? null,
                    'deactivated_by' => $isActive ? null : $admin->id,
                ],
            );

            $user->syncRoles(PermissionCatalog::ROLE_DRIVER);

            return Driver::query()->updateOrCreate(
                ['license_number' => $driverData['license_number']],
                [
                    'user_id' => $user->id,
                    'phone' => $driverData['phone'],
                    'status' => $driverData['status'],
                    'is_active' => $isActive,
                    'deactivated_at' => $driverData['deactivated_at'] ?? null,
                    'deactivated_by' => $isActive ? null : $admin->id,
                ],
            );
        });

        return Driver::query()
            ->whereIn('license_number', $drivers->pluck('license_number'))
            ->with('user')
            ->get()
            ->keyBy('license_number');
    }

    /**
     * @return Collection<int, Vehicle>
     */
    private function seedVehicles(User $admin): Collection
    {
        $vehicles = collect([
            ['plate_number' => 'RM-101', 'model' => 'Toyota Corolla Hybrid', 'year' => 2022, 'status' => Vehicle::STATUS_ACTIVE],
            ['plate_number' => 'RM-204', 'model' => 'Hyundai Tucson', 'year' => 2021, 'status' => Vehicle::STATUS_ACTIVE],
            ['plate_number' => 'RM-317', 'model' => 'Ford Transit Shuttle', 'year' => 2020, 'status' => Vehicle::STATUS_MAINTENANCE],
            ['plate_number' => 'RM-450', 'model' => 'Nissan Altima', 'year' => 2023, 'status' => Vehicle::STATUS_ACTIVE],
            ['plate_number' => 'RM-099', 'model' => 'Kia Rio', 'year' => 2017, 'status' => Vehicle::STATUS_RETIRED, 'deactivated_at' => now()->subDays(35)],
        ])->map(function (array $vehicleData) use ($admin): Vehicle {
            $isActive = $vehicleData['status'] !== Vehicle::STATUS_RETIRED;

            return Vehicle::query()->updateOrCreate(
                ['plate_number' => $vehicleData['plate_number']],
                [
                    'model' => $vehicleData['model'],
                    'year' => $vehicleData['year'],
                    'status' => $vehicleData['status'],
                    'is_active' => $isActive,
                    'deactivated_at' => $vehicleData['deactivated_at'] ?? null,
                    'deactivated_by' => $isActive ? null : $admin->id,
                ],
            );
        });

        return Vehicle::query()
            ->whereIn('plate_number', $vehicles->pluck('plate_number'))
            ->get()
            ->keyBy('plate_number');
    }

    /**
     * @param  Collection<int, Driver>  $drivers
     * @param  Collection<int, Vehicle>  $vehicles
     */
    private function seedAssignments(Collection $drivers, Collection $vehicles): void
    {
        $assignments = [
            ['driver' => 'DEMO-LAYLA-001', 'vehicle' => 'RM-101', 'assigned_at' => now()->subDays(42), 'unassigned_at' => null],
            ['driver' => 'DEMO-KARIM-002', 'vehicle' => 'RM-204', 'assigned_at' => now()->subDays(34), 'unassigned_at' => null],
            ['driver' => 'DEMO-NOUR-003', 'vehicle' => 'RM-317', 'assigned_at' => now()->subDays(29), 'unassigned_at' => null],
            ['driver' => 'DEMO-SAMIR-004', 'vehicle' => 'RM-450', 'assigned_at' => now()->subDays(21), 'unassigned_at' => null],
            ['driver' => 'DEMO-RANA-005', 'vehicle' => 'RM-099', 'assigned_at' => now()->subDays(110), 'unassigned_at' => now()->subDays(36)],
            ['driver' => 'DEMO-OMAR-006', 'vehicle' => 'RM-101', 'assigned_at' => now()->subDays(130), 'unassigned_at' => now()->subDays(60)],
        ];

        foreach ($assignments as $assignment) {
            DriverVehicle::query()->updateOrCreate(
                [
                    'driver_id' => $drivers->get($assignment['driver'])->id,
                    'vehicle_id' => $vehicles->get($assignment['vehicle'])->id,
                ],
                [
                    'assigned_at' => $assignment['assigned_at'],
                    'unassigned_at' => $assignment['unassigned_at'],
                ],
            );
        }
    }

    /**
     * @param  Collection<int, Driver>  $drivers
     * @param  Collection<int, Vehicle>  $vehicles
     * @return Collection<int, Incident>
     */
    private function seedIncidents(Collection $drivers, Collection $vehicles, User $monitor, User $admin): Collection
    {
        $incidents = collect([
            [
                'key' => 'campus-hard-braking',
                'driver' => 'DEMO-LAYLA-001',
                'vehicle' => 'RM-101',
                'type' => Incident::TYPE_UNSAFE_DRIVING,
                'severity' => Incident::SEVERITY_MEDIUM,
                'status' => Incident::STATUS_UNDER_REVIEW,
                'description' => 'DEMO: Hard braking near campus gate after a student crossed late.',
                'created_at' => now()->subHours(5),
            ],
            [
                'key' => 'north-exit-sideswipe',
                'driver' => 'DEMO-SAMIR-004',
                'vehicle' => 'RM-450',
                'type' => Incident::TYPE_CRASH,
                'severity' => Incident::SEVERITY_HIGH,
                'status' => Incident::STATUS_RESOLVED,
                'description' => 'DEMO: Side-swipe report at the north parking exit with unclear right-of-way.',
                'created_at' => now()->subDay(),
            ],
            [
                'key' => 'phone-distraction-complaint',
                'driver' => 'DEMO-SAMIR-004',
                'vehicle' => 'RM-450',
                'type' => Incident::TYPE_COMPLAINT,
                'severity' => Incident::SEVERITY_LOW,
                'status' => Incident::STATUS_RESOLVED,
                'description' => 'DEMO: Passenger complaint about repeated phone distraction during pickup.',
                'created_at' => now()->subDays(3),
            ],
            [
                'key' => 'service-road-collision',
                'driver' => 'DEMO-SAMIR-004',
                'vehicle' => 'RM-450',
                'type' => Incident::TYPE_CRASH,
                'severity' => Incident::SEVERITY_CRITICAL,
                'status' => Incident::STATUS_RESOLVED,
                'description' => 'DEMO: Critical collision at service road intersection after red-light violation.',
                'created_at' => now()->subDays(6),
            ],
            [
                'key' => 'pedestrian-near-miss',
                'driver' => 'DEMO-LAYLA-001',
                'vehicle' => 'RM-101',
                'type' => Incident::TYPE_NEAR_MISS,
                'severity' => Incident::SEVERITY_MEDIUM,
                'status' => Incident::STATUS_RESOLVED,
                'description' => 'DEMO: Near miss with pedestrian crossing between parked vehicles.',
                'created_at' => now()->subDays(8),
            ],
            [
                'key' => 'ring-road-lane-changes',
                'driver' => 'DEMO-SAMIR-004',
                'vehicle' => 'RM-450',
                'type' => Incident::TYPE_UNSAFE_DRIVING,
                'severity' => Incident::SEVERITY_MEDIUM,
                'status' => Incident::STATUS_RESOLVED,
                'description' => 'DEMO: Unsafe lane changes on ring road loop during evening traffic.',
                'created_at' => now()->subDays(10),
            ],
            [
                'key' => 'late-night-upload',
                'driver' => 'DEMO-KARIM-002',
                'vehicle' => 'RM-204',
                'type' => Incident::TYPE_UNSAFE_DRIVING,
                'severity' => Incident::SEVERITY_MEDIUM,
                'status' => Incident::STATUS_PENDING,
                'description' => 'DEMO: Late-night dashcam upload still analyzing after abrupt stop.',
                'created_at' => now()->subHours(14),
            ],
            [
                'key' => 'archived-retired-vehicle',
                'driver' => 'DEMO-RANA-005',
                'vehicle' => 'RM-099',
                'type' => Incident::TYPE_COMPLAINT,
                'severity' => Incident::SEVERITY_LOW,
                'status' => Incident::STATUS_INACTIVE,
                'description' => 'DEMO: Archived duplicate complaint for retired vehicle handoff.',
                'created_at' => now()->subDays(22),
                'deactivated_at' => now()->subDays(20),
            ],
        ])->map(function (array $incidentData) use ($admin, $drivers, $monitor, $vehicles): Incident {
            $isActive = $incidentData['status'] !== Incident::STATUS_INACTIVE;

            return Incident::query()->updateOrCreate(
                ['description' => $incidentData['description']],
                [
                    'driver_id' => $drivers->get($incidentData['driver'])->id,
                    'vehicle_id' => $vehicles->get($incidentData['vehicle'])->id,
                    'type' => $incidentData['type'],
                    'severity' => $incidentData['severity'],
                    'status' => $incidentData['status'],
                    'reported_by' => $monitor->id,
                    'is_active' => $isActive,
                    'deactivated_at' => $incidentData['deactivated_at'] ?? null,
                    'deactivated_by' => $isActive ? null : $admin->id,
                    'created_at' => $incidentData['created_at'],
                    'updated_at' => $incidentData['created_at']->copy()->addMinutes(12),
                ],
            );
        });

        return Incident::query()
            ->whereIn('description', $incidents->pluck('description'))
            ->get()
            ->keyBy(fn (Incident $incident): string => Str::after($incident->description, 'DEMO: '));
    }

    /**
     * @param  Collection<int, Incident>  $incidents
     */
    private function seedAnalyses(Collection $incidents): void
    {
        $analysisData = [
            'Hard braking near campus gate after a student crossed late.' => [
                'status' => AIAnalysis::STATUS_PROCESSING,
                'summary' => null,
                'detected_events' => null,
                'confidence_score' => null,
                'recommendation' => 'AI processing is still running. Keep this incident in the monitor queue.',
                'suggested_fault_decision' => null,
                'fault_confidence_score' => null,
                'fault_reasoning' => null,
            ],
            'Side-swipe report at the north parking exit with unclear right-of-way.' => [
                'status' => AIAnalysis::STATUS_COMPLETED,
                'summary' => 'Dashcam context suggests both vehicles entered the merge area at nearly the same time.',
                'detected_events' => 'side approach, close pass, uncertain lane priority',
                'confidence_score' => 0.74,
                'recommendation' => 'Review road position and mirror checks before making a final decision.',
                'suggested_fault_decision' => IncidentReview::FAULT_SHARED,
                'fault_confidence_score' => 0.61,
                'fault_reasoning' => 'Available evidence points to shared responsibility, but final decision requires monitor review.',
            ],
            'Passenger complaint about repeated phone distraction during pickup.' => [
                'status' => AIAnalysis::STATUS_COMPLETED,
                'summary' => 'Complaint text references repeated distracted behavior during passenger pickup.',
                'detected_events' => 'reported phone distraction, passenger complaint',
                'confidence_score' => 0.58,
                'recommendation' => 'Use passenger statement and driver history to decide fault.',
                'suggested_fault_decision' => IncidentReview::FAULT_DRIVER,
                'fault_confidence_score' => 0.54,
                'fault_reasoning' => 'The complaint is consistent with driver distraction, though evidence is testimonial.',
            ],
            'Critical collision at service road intersection after red-light violation.' => [
                'status' => AIAnalysis::STATUS_COMPLETED,
                'summary' => 'The report indicates a severe intersection collision with a likely signal violation.',
                'detected_events' => 'intersection collision, red-light violation, critical severity',
                'confidence_score' => 0.86,
                'recommendation' => 'Prioritize supervisor review and driver coaching before returning to service.',
                'suggested_fault_decision' => IncidentReview::FAULT_DRIVER,
                'fault_confidence_score' => 0.79,
                'fault_reasoning' => 'Narrative evidence strongly suggests driver responsibility for the signal violation.',
            ],
            'Near miss with pedestrian crossing between parked vehicles.' => [
                'status' => AIAnalysis::STATUS_COMPLETED,
                'summary' => 'The near miss appears related to reduced visibility from parked vehicles.',
                'detected_events' => 'pedestrian near miss, obstructed sightline',
                'confidence_score' => 0.68,
                'recommendation' => 'Coach defensive speed reduction in high-pedestrian areas.',
                'suggested_fault_decision' => IncidentReview::FAULT_OTHER_PARTY,
                'fault_confidence_score' => 0.47,
                'fault_reasoning' => 'Evidence suggests the pedestrian entered suddenly, but confidence remains limited.',
            ],
            'Unsafe lane changes on ring road loop during evening traffic.' => [
                'status' => AIAnalysis::STATUS_FAILED,
                'summary' => null,
                'detected_events' => null,
                'confidence_score' => null,
                'recommendation' => 'AI analysis failed. Manual review should continue without advisory observations.',
                'suggested_fault_decision' => null,
                'fault_confidence_score' => null,
                'fault_reasoning' => null,
            ],
            'Late-night dashcam upload still analyzing after abrupt stop.' => [
                'status' => AIAnalysis::STATUS_AI_ANALYZING,
                'summary' => null,
                'detected_events' => null,
                'confidence_score' => null,
                'recommendation' => 'AI analysis has started and is waiting for final output.',
                'suggested_fault_decision' => null,
                'fault_confidence_score' => null,
                'fault_reasoning' => null,
            ],
        ];

        foreach ($analysisData as $descriptionTail => $attributes) {
            $incident = $incidents->get($descriptionTail);

            AIAnalysis::query()->updateOrCreate(
                ['incident_id' => $incident->id],
                [
                    ...$attributes,
                    'media_fingerprint' => hash('sha256', $incident->description),
                    'raw_response' => [
                        'source' => 'demo_seed',
                        'scenario' => Str::slug($descriptionTail),
                        'seeded_at' => Carbon::parse($incident->created_at)->toDateString(),
                    ],
                    'is_active' => true,
                    'deactivated_at' => null,
                    'deactivated_by' => null,
                    'created_at' => $incident->created_at,
                    'updated_at' => Carbon::parse($incident->created_at)->addMinutes(18),
                ],
            );
        }
    }

    /**
     * @param  Collection<int, Incident>  $incidents
     */
    private function seedReviews(Collection $incidents, User $monitor): void
    {
        $reviews = [
            'Side-swipe report at the north parking exit with unclear right-of-way.' => [
                'fault_decision' => IncidentReview::FAULT_DRIVER,
                'notes' => 'Monitor review found the demo driver merged too aggressively and assigned driver fault.',
                'reviewed_at' => now()->subHours(20),
            ],
            'Passenger complaint about repeated phone distraction during pickup.' => [
                'fault_decision' => IncidentReview::FAULT_DRIVER,
                'notes' => 'Monitor confirmed the passenger report and marked this as driver fault for coaching.',
                'reviewed_at' => now()->subDays(2),
            ],
            'Critical collision at service road intersection after red-light violation.' => [
                'fault_decision' => IncidentReview::FAULT_DRIVER,
                'notes' => 'Signal timing and report narrative support driver fault. Escalated for safety follow-up.',
                'reviewed_at' => now()->subDays(5),
            ],
            'Near miss with pedestrian crossing between parked vehicles.' => [
                'fault_decision' => IncidentReview::FAULT_OTHER_PARTY,
                'notes' => 'Pedestrian entered from between parked cars. Driver response was acceptable.',
                'reviewed_at' => now()->subDays(7),
            ],
            'Unsafe lane changes on ring road loop during evening traffic.' => [
                'fault_decision' => IncidentReview::FAULT_DRIVER,
                'notes' => 'Manual review found repeated unsafe lane changes despite the failed AI analysis.',
                'reviewed_at' => now()->subDays(9),
            ],
        ];

        foreach ($reviews as $descriptionTail => $reviewData) {
            $incident = $incidents->get($descriptionTail);

            IncidentReview::query()->updateOrCreate(
                ['incident_id' => $incident->id],
                [
                    'reviewed_by' => $monitor->id,
                    'fault_decision' => $reviewData['fault_decision'],
                    'notes' => $reviewData['notes'],
                    'reviewed_at' => $reviewData['reviewed_at'],
                    'is_active' => true,
                    'deactivated_at' => null,
                    'deactivated_by' => null,
                    'created_at' => $reviewData['reviewed_at'],
                    'updated_at' => $reviewData['reviewed_at'],
                ],
            );
        }
    }

    /**
     * @param  Collection<int, Incident>  $incidents
     */
    private function seedMedia(Collection $incidents, User $monitor): void
    {
        $path = collect(Storage::disk('public')->files('incident-media'))
            ->first(fn (string $path): bool => is_file(Storage::disk('public')->path($path)));

        if ($path === null) {
            return;
        }

        $fullPath = Storage::disk('public')->path($path);
        $mimeType = mime_content_type($fullPath) ?: 'video/mp4';
        $fileType = str_starts_with($mimeType, 'image/') ? IncidentMedia::TYPE_IMAGE : IncidentMedia::TYPE_VIDEO;

        $incidents
            ->take(3)
            ->each(function (Incident $incident) use ($fileType, $fullPath, $mimeType, $monitor, $path): void {
                IncidentMedia::query()->updateOrCreate(
                    [
                        'incident_id' => $incident->id,
                        'sha256_hash' => hash_file('sha256', $fullPath),
                    ],
                    [
                        'file_path' => $path,
                        'original_name' => basename($path),
                        'file_type' => $fileType,
                        'mime_type' => $mimeType,
                        'size' => filesize($fullPath) ?: 0,
                        'uploaded_by' => $monitor->id,
                        'is_active' => true,
                        'deactivated_at' => null,
                        'deactivated_by' => null,
                    ],
                );
            });
    }
}
