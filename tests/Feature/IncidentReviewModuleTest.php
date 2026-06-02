<?php

use App\Models\Driver;
use App\Models\Incident;
use App\Models\IncidentMedia;
use App\Models\IncidentReview;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    seedRolesAndPermissions();
});

test('review module uses tabs and a single sidebar link', function () {
    $admin = createUserWithRole('admin');
    $monitor = createUserWithRole('monitor');

    $this->actingAs($admin)
        ->get(route('dashboard.admin'))
        ->assertSuccessful()
        ->assertSeeText('Review Center')
        ->assertDontSeeText('AI Analyses');

    $this->actingAs($monitor)
        ->get(route('incident-reviews.index'))
        ->assertSuccessful()
        ->assertSeeText('Review Center')
        ->assertSeeText('Pending Reviews')
        ->assertSeeText('Completed Reviews')
        ->assertSeeText('AI Processing');

    $this->actingAs($monitor)
        ->get(route('dashboard.monitor'))
        ->assertSuccessful()
        ->assertSeeText('Review Center')
        ->assertDontSeeText('AI Analyses')
        ->assertDontSeeText('Pending Reviews');

    $driver = createUserWithRole('driver');

    $this->actingAs($driver)
        ->get(route('dashboard.driver'))
        ->assertSuccessful()
        ->assertDontSeeText('Review Center')
        ->assertDontSeeText('AI Analyses');
});

test('review center compact tables use truncated text and icon actions', function () {
    $monitor = createUserWithRole('monitor');
    $incident = createIncidentForDriver('This long pending incident description should collapse quickly in table rows.');

    $this->actingAs($monitor)
        ->get(route('incident-reviews.index'))
        ->assertSuccessful()
        ->assertSeeText('This long pending incident description...')
        ->assertSee('title="This long pending incident description should collapse quickly in table rows."', false)
        ->assertSee('aria-label="Open incident"', false)
        ->assertSee('aria-label="Start review"', false)
        ->assertDontSeeText('Open incident')
        ->assertDontSeeText('Start review');

    expect($incident->exists)->toBeTrue();
});

test('monitor can start review and incident becomes under review', function () {
    $monitor = createUserWithRole('monitor');
    $incident = createIncidentForDriver('Review start incident.');

    $this->actingAs($monitor)
        ->patch(route('incidents.review.start', $incident))
        ->assertRedirect(route('incidents.show', $incident));

    expect($incident->fresh()->status)->toBe(Incident::STATUS_UNDER_REVIEW);
});

test('monitor can submit final review and resolve incident', function () {
    $monitor = createUserWithRole('monitor');
    $incident = createIncidentForDriver('Resolve incident.');

    $this->actingAs($monitor)
        ->post(route('incidents.reviews.store', $incident), [
            'fault_decision' => IncidentReview::FAULT_DRIVER,
            'notes' => 'Driver entered the intersection too quickly.',
        ])
        ->assertRedirect(route('incidents.show', $incident));

    $review = IncidentReview::query()->where('incident_id', $incident->id)->firstOrFail();

    expect($incident->fresh()->status)->toBe(Incident::STATUS_RESOLVED)
        ->and($incident->fresh()->is_active)->toBeTrue()
        ->and($review->reviewed_by)->toBe($monitor->id)
        ->and($review->fault_decision)->toBe(IncidentReview::FAULT_DRIVER)
        ->and($review->is_active)->toBeTrue()
        ->and($review->reviewed_at)->not->toBeNull();
});

test('admin can submit final review and resolve incident', function () {
    $admin = createUserWithRole('admin');
    $incident = createIncidentForDriver('Admin resolve incident.');

    $this->actingAs($admin)
        ->post(route('incidents.reviews.store', $incident), [
            'fault_decision' => IncidentReview::FAULT_UNCLEAR,
            'notes' => 'Admin submitted the final human review.',
        ])
        ->assertRedirect(route('incidents.show', $incident));

    $review = IncidentReview::query()->where('incident_id', $incident->id)->firstOrFail();

    expect($incident->fresh()->status)->toBe(Incident::STATUS_RESOLVED)
        ->and($review->reviewed_by)->toBe($admin->id)
        ->and($review->fault_decision)->toBe(IncidentReview::FAULT_UNCLEAR)
        ->and($review->is_active)->toBeTrue();
});

test('driver cannot submit review', function () {
    $driverUser = createUserWithRole('driver');
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
    $incident = Incident::factory()->create([
        'driver_id' => $driver->id,
        'reported_by' => $driverUser->id,
    ]);

    $this->actingAs($driverUser)
        ->post(route('incidents.reviews.store', $incident), [
            'fault_decision' => IncidentReview::FAULT_UNCLEAR,
            'notes' => 'Driver attempted to review own incident.',
        ])
        ->assertForbidden();

    expect($incident->fresh()->status)->toBe(Incident::STATUS_PENDING)
        ->and(IncidentReview::query()->where('incident_id', $incident->id)->exists())->toBeFalse();
});

test('inactive incidents are hidden from default pending reviews tab', function () {
    $monitor = createUserWithRole('monitor');
    createIncidentForDriver('Visible pending incident.');
    Incident::factory()->inactive()->create(['description' => 'Hidden inactive incident.']);

    $this->actingAs($monitor)
        ->get(route('incident-reviews.index'))
        ->assertSuccessful()
        ->assertSeeText('Visible pending incident.')
        ->assertDontSeeText('Hidden inactive incident.');
});

test('incident detail shows final review state clearly', function () {
    $monitor = createUserWithRole('monitor');
    $incident = createIncidentForDriver('Review display incident.');

    $this->actingAs($monitor)
        ->get(route('incidents.show', $incident))
        ->assertSuccessful()
        ->assertSeeText('No final review exists yet.');

    IncidentReview::factory()->create([
        'incident_id' => $incident->id,
        'reviewed_by' => $monitor->id,
        'fault_decision' => IncidentReview::FAULT_SHARED,
        'notes' => 'Shared responsibility based on footage.',
    ]);
    $incident->update(['status' => Incident::STATUS_RESOLVED]);

    $this->actingAs($monitor)
        ->get(route('incidents.show', $incident))
        ->assertSuccessful()
        ->assertSeeText('Shared fault')
        ->assertSeeText('Shared responsibility based on footage.');
});

test('uploaded media is viewable by authorized users only', function () {
    Storage::fake('public');

    $driverUser = createUserWithRole('driver');
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
    $incident = Incident::factory()->create([
        'driver_id' => $driver->id,
        'reported_by' => $driverUser->id,
    ]);
    Storage::disk('public')->put('incident-media/evidence.jpg', 'evidence');
    $media = IncidentMedia::factory()->create([
        'incident_id' => $incident->id,
        'file_path' => 'incident-media/evidence.jpg',
        'original_name' => 'evidence.jpg',
        'file_type' => IncidentMedia::TYPE_IMAGE,
        'mime_type' => 'image/jpeg',
    ]);
    $monitor = createUserWithRole('monitor');

    $this->actingAs($monitor)
        ->get(route('incident-media.show', $media))
        ->assertSuccessful();

    $this->actingAs($driverUser)
        ->get(route('incident-media.show', $media))
        ->assertSuccessful();

    $otherDriverUser = createUserWithRole('driver');
    Driver::factory()->create(['user_id' => $otherDriverUser->id]);

    $this->actingAs($otherDriverUser)
        ->get(route('incident-media.show', $media))
        ->assertForbidden();
});

test('deactivating review reopens incident to under review', function () {
    $monitor = createUserWithRole('monitor');
    $incident = createIncidentForDriver('Review deactivation incident.', Incident::STATUS_RESOLVED);
    $review = IncidentReview::factory()->create([
        'incident_id' => $incident->id,
        'reviewed_by' => $monitor->id,
    ]);

    $this->actingAs($monitor)
        ->patch(route('incident-reviews.deactivate', $review))
        ->assertRedirect();

    expect($review->fresh()->is_active)->toBeFalse()
        ->and($review->fresh()->deactivated_by)->toBe($monitor->id)
        ->and($incident->fresh()->status)->toBe(Incident::STATUS_UNDER_REVIEW);

    $this->actingAs($monitor)
        ->patch(route('incident-reviews.reactivate', $review))
        ->assertRedirect();

    expect($review->fresh()->is_active)->toBeTrue()
        ->and($incident->fresh()->status)->toBe(Incident::STATUS_RESOLVED);
});

function createIncidentForDriver(string $description, string $status = Incident::STATUS_PENDING): Incident
{
    $driverUser = createUserWithRole('driver');
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
    $vehicle = Vehicle::factory()->create();

    return Incident::factory()->create([
        'driver_id' => $driver->id,
        'vehicle_id' => $vehicle->id,
        'reported_by' => $driverUser->id,
        'description' => $description,
        'status' => $status,
    ]);
}
