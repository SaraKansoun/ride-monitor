<?php

use App\Jobs\AnalyzeIncidentJob;
use App\Models\AIAnalysis;
use App\Models\Driver;
use App\Models\DriverVehicle;
use App\Models\Incident;
use App\Models\IncidentMedia;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AIIncidentAnalysisService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    seedRolesAndPermissions();
});

test('incident with media creates pending ai analysis and dispatches analyze job', function () {
    Queue::fake();
    Storage::fake('public');

    [$driverUser, $driver, $vehicle] = createAiAnalysisDriverContext();

    $this->actingAs($driverUser)
        ->post(route('incidents.store'), [
            'type' => Incident::TYPE_UNSAFE_DRIVING,
            'description' => 'Possible short following distance captured by dashcam.',
            'vehicle_id' => $vehicle->id,
            'media' => [
                UploadedFile::fake()->create('dashcam.mp4', 1024, 'video/mp4'),
            ],
        ])
        ->assertRedirect();

    $incident = Incident::query()
        ->where('description', 'Possible short following distance captured by dashcam.')
        ->with('aiAnalyses')
        ->firstOrFail();
    $analysis = $incident->aiAnalyses->first();

    expect($incident->driver_id)->toBe($driver->id)
        ->and($incident->aiAnalyses)->toHaveCount(1)
        ->and($analysis)->toBeInstanceOf(AIAnalysis::class)
        ->and($analysis->status)->toBe(AIAnalysis::STATUS_PENDING)
        ->and($analysis->is_active)->toBeTrue();

    Queue::assertPushed(
        AnalyzeIncidentJob::class,
        fn (AnalyzeIncidentJob $job): bool => $analysis instanceof AIAnalysis
            && $job->aiAnalysisId === $analysis->id
    );
});

test('incident without media creates no ai analysis and dispatches no analyze job', function () {
    Queue::fake();

    [$driverUser, $driver, $vehicle] = createAiAnalysisDriverContext();

    $this->actingAs($driverUser)
        ->post(route('incidents.store'), [
            'type' => Incident::TYPE_COMPLAINT,
            'description' => 'Passenger submitted a complaint without media.',
            'vehicle_id' => $vehicle->id,
        ])
        ->assertRedirect();

    $incident = Incident::query()
        ->where('description', 'Passenger submitted a complaint without media.')
        ->with('aiAnalyses')
        ->firstOrFail();

    expect($incident->driver_id)->toBe($driver->id)
        ->and($incident->aiAnalyses)->toHaveCount(0);

    Queue::assertNotPushed(AnalyzeIncidentJob::class);
});

test('analyze incident job updates pending analysis with cautious advisory output', function () {
    $analysis = createMediaBackedAnalysis('Hard braking and close following shown in dashcam footage.');

    (new AnalyzeIncidentJob($analysis->id))->handle(app(AIIncidentAnalysisService::class));

    $analysis->refresh();

    expect($analysis->status)->toBe(AIAnalysis::STATUS_COMPLETED)
        ->and($analysis->summary)->toContain('appears to')
        ->and($analysis->summary)->toContain('may indicate')
        ->and($analysis->summary)->toContain('Manual review recommended')
        ->and($analysis->detected_events)->toContain('possible sudden braking')
        ->and($analysis->detected_events)->toContain('short following distance may indicate')
        ->and($analysis->recommendation)->toContain('advisory only')
        ->and($analysis->confidence_score)->toBeGreaterThan(0)
        ->and($analysis->raw_response['source'])->toBe('local_metadata')
        ->and($analysis->summary)->not->toContain('legally guilty')
        ->and($analysis->summary)->not->toContain('fault is confirmed')
        ->and($analysis->summary)->not->toContain('this proves');
});

test('failed ai job marks analysis as failed with useful error context', function () {
    $analysis = createMediaBackedAnalysis('Video cannot be analyzed by the local service.');
    $failingService = new class extends AIIncidentAnalysisService
    {
        public function analyze(Incident $incident): array
        {
            throw new RuntimeException('Local analyzer unavailable.');
        }
    };

    (new AnalyzeIncidentJob($analysis->id))->handle($failingService);

    $analysis->refresh();

    expect($analysis->status)->toBe(AIAnalysis::STATUS_FAILED)
        ->and($analysis->summary)->toBe('AI analysis failed. Manual review recommended.')
        ->and($analysis->recommendation)->toContain('Manual review recommended')
        ->and($analysis->raw_response['source'])->toBe('local_metadata')
        ->and($analysis->raw_response['error']['message'])->toContain('Local analyzer unavailable');
});

test('admin and monitor can view ai analyses module but driver cannot', function () {
    $admin = createUserWithRole('admin');
    $monitor = createUserWithRole('monitor');
    $driver = createUserWithRole('driver');
    createMediaBackedAnalysis('Module visibility analysis incident.');

    $this->actingAs($admin)
        ->get(route('ai-analyses.index'))
        ->assertSuccessful()
        ->assertSeeText('AI Analyses')
        ->assertSeeText('Module visibility analysis incident.');

    $this->actingAs($monitor)
        ->get(route('dashboard.monitor'))
        ->assertSuccessful()
        ->assertSeeText('AI Analyses');

    $this->actingAs($driver)
        ->get(route('dashboard.driver'))
        ->assertSuccessful()
        ->assertDontSeeText('AI Analyses');

    $this->actingAs($driver)
        ->get(route('ai-analyses.index'))
        ->assertForbidden();
});

test('owning driver can see ai analysis on incident detail and another driver is forbidden', function () {
    [$driverUser, $driver] = createAiAnalysisDriverContext();
    [$otherDriverUser] = createAiAnalysisDriverContext();
    $incident = Incident::factory()->create([
        'driver_id' => $driver->id,
        'reported_by' => $driverUser->id,
        'description' => 'Owning driver AI visible incident.',
    ]);
    AIAnalysis::factory()->completed()->create(['incident_id' => $incident->id]);

    $this->actingAs($driverUser)
        ->get(route('incidents.show', $incident))
        ->assertSuccessful()
        ->assertSeeText('AI analysis')
        ->assertSeeText('AI observations are advisory only');

    $this->actingAs($otherDriverUser)
        ->get(route('incidents.show', $incident))
        ->assertForbidden();
});

test('ai analysis ui has no fake generation controls', function () {
    $monitor = createUserWithRole('monitor');
    $analysis = createMediaBackedAnalysis('Read only AI analysis incident.');

    $this->actingAs($monitor)
        ->get(route('ai-analyses.index'))
        ->assertSuccessful()
        ->assertSeeText('View incident')
        ->assertDontSeeText('Generate fake')
        ->assertDontSeeText('Reactivate');

    $this->actingAs($monitor)
        ->get(route('incidents.show', $analysis->incident))
        ->assertSuccessful()
        ->assertDontSeeText('Generate fake analysis');

    expect(Route::has('ai-analyses.generate-fake'))->toBeFalse()
        ->and(Route::has('ai-analyses.deactivate'))->toBeTrue()
        ->and(Route::has('ai-analyses.reactivate'))->toBeTrue()
        ->and(Route::has('ai-analyses.destroy'))->toBeFalse();
});

test('deactivated ai analyses are hidden from default module and incident detail', function () {
    $monitor = createUserWithRole('monitor');
    $activeIncident = Incident::factory()->create(['description' => 'Active AI analysis incident.']);
    $inactiveIncident = Incident::factory()->create(['description' => 'Inactive AI analysis incident.']);
    AIAnalysis::factory()->completed()->create(['incident_id' => $activeIncident->id]);
    AIAnalysis::factory()->completed()->inactive()->create(['incident_id' => $inactiveIncident->id]);

    $this->actingAs($monitor)
        ->get(route('ai-analyses.index'))
        ->assertSuccessful()
        ->assertSeeText('Active AI analysis incident.')
        ->assertDontSeeText('Inactive AI analysis incident.');

    $this->actingAs($monitor)
        ->get(route('incidents.show', $inactiveIncident))
        ->assertSuccessful()
        ->assertSeeText('No active AI analysis exists for this incident.')
        ->assertDontSeeText('AI observations are advisory only');
});

/**
 * @return array{0: User, 1: Driver, 2: Vehicle}
 */
function createAiAnalysisDriverContext(): array
{
    $driverUser = createUserWithRole('driver');
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
    $vehicle = Vehicle::factory()->create();

    DriverVehicle::factory()->create([
        'driver_id' => $driver->id,
        'vehicle_id' => $vehicle->id,
    ]);

    return [$driverUser, $driver, $vehicle];
}

function createMediaBackedAnalysis(string $description): AIAnalysis
{
    [$driverUser, $driver, $vehicle] = createAiAnalysisDriverContext();
    $incident = Incident::factory()->create([
        'driver_id' => $driver->id,
        'vehicle_id' => $vehicle->id,
        'reported_by' => $driverUser->id,
        'type' => Incident::TYPE_UNSAFE_DRIVING,
        'description' => $description,
    ]);

    IncidentMedia::factory()->create([
        'incident_id' => $incident->id,
        'file_type' => IncidentMedia::TYPE_VIDEO,
        'mime_type' => 'video/mp4',
        'original_name' => 'dashcam.mp4',
        'size' => 1_500_000,
    ]);

    return AIAnalysis::factory()->create(['incident_id' => $incident->id]);
}
