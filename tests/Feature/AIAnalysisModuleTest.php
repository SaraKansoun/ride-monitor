<?php

use App\Jobs\AnalyzeIncidentJob;
use App\Models\AIAnalysis;
use App\Models\Driver;
use App\Models\DriverVehicle;
use App\Models\Incident;
use App\Models\IncidentMedia;
use App\Models\IncidentReview;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AIIncidentAnalysisService;
use App\Services\DashcamAnalysisResult;
use App\Services\LocalDashcamAnalysisService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    seedRolesAndPermissions();
});

test('incident with video creates processing ai analysis and dispatches analyze job', function () {
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
        ->and($analysis->status)->toBe(AIAnalysis::STATUS_PROCESSING)
        ->and($analysis->is_active)->toBeTrue()
        ->and($analysis->media_fingerprint)->not->toBeNull()
        ->and($incident->media->first()->sha256_hash)->not->toBeNull();

    Queue::assertPushed(
        AnalyzeIncidentJob::class,
        fn (AnalyzeIncidentJob $job): bool => $analysis instanceof AIAnalysis
            && $job->aiAnalysisId === $analysis->id
    );
});

test('same dashcam media fingerprint reuses completed analysis and avoids another queued job', function () {
    Queue::fake();
    Storage::fake('public');

    [$driverUser, $driver, $vehicle] = createAiAnalysisDriverContext();
    $videoContent = 'same-demo-dashcam-video';

    $this->actingAs($driverUser)
        ->post(route('incidents.store'), [
            'type' => Incident::TYPE_UNSAFE_DRIVING,
            'description' => 'First reusable dashcam upload.',
            'vehicle_id' => $vehicle->id,
            'media' => [
                UploadedFile::fake()->createWithContent('dashcam.mp4', $videoContent)->mimeType('video/mp4'),
            ],
        ])
        ->assertRedirect();

    $firstAnalysis = Incident::query()
        ->where('description', 'First reusable dashcam upload.')
        ->firstOrFail()
        ->aiAnalyses()
        ->firstOrFail();

    $firstAnalysis->update([
        'summary' => 'The selected dashcam frames appear to show possible unsafe driving. Manual review recommended.',
        'detected_events' => 'possible unsafe driving',
        'confidence_score' => 0.74,
        'recommendation' => 'AI observations are advisory only. Manual review recommended.',
        'suggested_fault_decision' => IncidentReview::FAULT_DRIVER,
        'fault_confidence_score' => 0.61,
        'fault_reasoning' => 'The selected media appears to show possible driver contribution, but manual review is required.',
        'raw_response' => ['source' => 'openai_responses', 'response_id' => 'resp_reused'],
        'status' => AIAnalysis::STATUS_COMPLETED,
    ]);

    $this->actingAs($driverUser)
        ->post(route('incidents.store'), [
            'type' => Incident::TYPE_UNSAFE_DRIVING,
            'description' => 'Second reusable dashcam upload.',
            'vehicle_id' => $vehicle->id,
            'media' => [
                UploadedFile::fake()->createWithContent('dashcam.mp4', $videoContent)->mimeType('video/mp4'),
            ],
        ])
        ->assertRedirect();

    $secondAnalysis = Incident::query()
        ->where('description', 'Second reusable dashcam upload.')
        ->firstOrFail()
        ->aiAnalyses()
        ->firstOrFail();

    expect($secondAnalysis->status)->toBe(AIAnalysis::STATUS_COMPLETED)
        ->and($secondAnalysis->summary)->toBe($firstAnalysis->summary)
        ->and($secondAnalysis->suggested_fault_decision)->toBe(IncidentReview::FAULT_DRIVER)
        ->and($secondAnalysis->fault_confidence_score)->toBe(0.61)
        ->and($secondAnalysis->fault_reasoning)->toBe('The selected media appears to show possible driver contribution, but manual review is required.')
        ->and($secondAnalysis->media_fingerprint)->toBe($firstAnalysis->media_fingerprint)
        ->and($secondAnalysis->raw_response['reuse']['reused_from_analysis_id'])->toBe($firstAnalysis->id);

    Queue::assertPushed(AnalyzeIncidentJob::class, 1);
});

test('document only incident creates no ai analysis and dispatches no analyze job', function () {
    Queue::fake();
    Storage::fake('public');

    [$driverUser, $driver, $vehicle] = createAiAnalysisDriverContext();

    $this->actingAs($driverUser)
        ->post(route('incidents.store'), [
            'type' => Incident::TYPE_COMPLAINT,
            'description' => 'Passenger submitted a complaint with a PDF statement.',
            'vehicle_id' => $vehicle->id,
            'media' => [
                UploadedFile::fake()->create('statement.pdf', 128, 'application/pdf'),
            ],
        ])
        ->assertRedirect();

    $incident = Incident::query()
        ->where('description', 'Passenger submitted a complaint with a PDF statement.')
        ->with('aiAnalyses')
        ->firstOrFail();

    expect($incident->driver_id)->toBe($driver->id)
        ->and($incident->aiAnalyses)->toHaveCount(0);

    Queue::assertNotPushed(AnalyzeIncidentJob::class);
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

test('analyze incident job updates pending analysis with openai cautious advisory output', function () {
    Storage::fake('public');
    configureOpenAIForTests();

    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response(openAIAnalysisResponse(), 200),
    ]);

    $analysis = createImageBackedAnalysis('Hard braking and close following shown in dashcam image.');

    (new AnalyzeIncidentJob($analysis->id))->handle(app(AIIncidentAnalysisService::class));

    $analysis->refresh();

    expect($analysis->status)->toBe(AIAnalysis::STATUS_COMPLETED)
        ->and($analysis->summary)->toContain('appears to')
        ->and($analysis->summary)->toContain('may indicate')
        ->and($analysis->summary)->toContain('Manual review recommended')
        ->and($analysis->detected_events)->toContain('possible sudden braking')
        ->and($analysis->detected_events)->toContain('short following distance may indicate')
        ->and($analysis->recommendation)->toContain('advisory only')
        ->and($analysis->confidence_score)->toBe(0.72)
        ->and($analysis->suggested_fault_decision)->toBe(IncidentReview::FAULT_SHARED)
        ->and($analysis->fault_confidence_score)->toBe(0.64)
        ->and($analysis->fault_reasoning)->toContain('appears to')
        ->and($analysis->incident->status)->toBe(Incident::STATUS_PENDING)
        ->and($analysis->incident->driver->score()->firstOrFail()->score)->toBe(100)
        ->and($analysis->raw_response['source'])->toBe('openai_responses')
        ->and($analysis->raw_response['response_id'])->toBe('resp_test')
        ->and($analysis->raw_response['media'][0]['visual_input_count'])->toBe(1)
        ->and($analysis->summary)->not->toContain('legally guilty')
        ->and($analysis->summary)->not->toContain('fault is confirmed')
        ->and($analysis->summary)->not->toContain('this proves');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.openai.com/v1/responses'
        && $request['model'] === 'gpt-5.4-mini'
        && data_get($request->data(), 'input.0.content.1.type') === 'input_image'
        && str_starts_with((string) data_get($request->data(), 'input.0.content.1.image_url'), 'data:image/jpeg;base64,')
        && data_get($request->data(), 'text.format.type') === 'json_schema'
        && data_get($request->data(), 'text.format.schema.properties.suggested_fault_decision.enum') === IncidentReview::FAULT_DECISIONS);
});

test('analyze incident job moves through processing and ai analyzing statuses before completion', function () {
    $analysis = createMediaBackedAnalysis('Status transition dashcam analysis.');
    $analysis->update(['status' => AIAnalysis::STATUS_PENDING]);

    $service = new class extends AIIncidentAnalysisService
    {
        public function __construct() {}

        /**
         * @return array<string, mixed>
         */
        public function analyze(Incident $incident, ?callable $markAiAnalyzing = null): array
        {
            $analysis = AIAnalysis::query()->where('incident_id', $incident->id)->firstOrFail();

            expect($analysis->status)->toBe(AIAnalysis::STATUS_PROCESSING);

            $markAiAnalyzing?->__invoke();

            expect($analysis->fresh()->status)->toBe(AIAnalysis::STATUS_AI_ANALYZING);

            return [
                'summary' => 'The selected dashcam frame appears to show possible risk. Manual review recommended.',
                'detected_events' => 'possible risky driving',
                'confidence_score' => 0.66,
                'recommendation' => 'AI observations are advisory only. Manual review recommended.',
                'suggested_fault_decision' => IncidentReview::FAULT_UNCLEAR,
                'fault_confidence_score' => 0.25,
                'fault_reasoning' => 'The media appears to need human review before fault can be assessed.',
                'raw_response' => ['source' => 'test_status_transition'],
            ];
        }
    };

    (new AnalyzeIncidentJob($analysis->id))->handle($service);

    expect($analysis->fresh()->status)->toBe(AIAnalysis::STATUS_COMPLETED)
        ->and($analysis->fresh()->summary)->toContain('appears to');
});

test('local dashcam low risk result completes analysis without calling openai', function () {
    Storage::fake('public');
    configureOpenAIForTests();
    config()->set('services.dashcam.local_analysis_enabled', true);
    Http::fake();

    app()->instance(LocalDashcamAnalysisService::class, new class extends LocalDashcamAnalysisService
    {
        public function analyze(Incident $incident): DashcamAnalysisResult
        {
            return new DashcamAnalysisResult(
                localAnalysisEnabled: true,
                shouldUseOpenAI: false,
                mediaId: null,
                riskScore: 0.18,
                confidenceScore: 0.82,
                summary: 'Local dashcam screening appears to show no strong high-risk visual event. Manual review recommended if concerns remain.',
                detectedEvents: ['no high-risk local indicators detected'],
                selectedFrames: [],
                temporaryDirectories: [],
                rawResponse: ['source' => 'local_opencv_yolo', 'risk_score' => 0.18],
            );
        }
    });

    $analysis = createImageBackedAnalysis('Low risk local dashcam screening.');

    (new AnalyzeIncidentJob($analysis->id))->handle(app(AIIncidentAnalysisService::class));

    $analysis->refresh();

    expect($analysis->status)->toBe(AIAnalysis::STATUS_COMPLETED)
        ->and($analysis->summary)->toContain('Local dashcam screening')
        ->and($analysis->suggested_fault_decision)->toBe(IncidentReview::FAULT_UNCLEAR)
        ->and($analysis->fault_confidence_score)->toBe(0.0)
        ->and($analysis->fault_reasoning)->toContain('does not decide fault')
        ->and($analysis->raw_response['openai']['skipped'])->toBeTrue()
        ->and($analysis->raw_response['local_analysis']['risk_score'])->toBe(0.18);

    Http::assertNothingSent();
});

test('local dashcam high risk result sends only selected frames to openai', function () {
    Storage::fake('public');
    configureOpenAIForTests();
    config()->set('services.dashcam.local_analysis_enabled', true);
    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response(openAIAnalysisResponse([
            'detected_events' => 'possible collision risk, possible unsafe lane change',
        ]), 200),
    ]);

    Storage::disk('public')->put('selected/frame-a.jpg', 'frame-a');
    Storage::disk('public')->put('selected/frame-b.jpg', 'frame-b');
    $frameA = Storage::disk('public')->path('selected/frame-a.jpg');
    $frameB = Storage::disk('public')->path('selected/frame-b.jpg');

    app()->instance(LocalDashcamAnalysisService::class, new class($frameA, $frameB) extends LocalDashcamAnalysisService
    {
        public function __construct(private string $frameA, private string $frameB) {}

        public function analyze(Incident $incident): DashcamAnalysisResult
        {
            return new DashcamAnalysisResult(
                localAnalysisEnabled: true,
                shouldUseOpenAI: true,
                mediaId: 99,
                riskScore: 0.88,
                confidenceScore: 0.71,
                summary: 'Local dashcam screening appears to show possible dangerous driving indicators. Manual review recommended.',
                detectedEvents: ['possible collision or near-miss risk'],
                selectedFrames: [
                    ['path' => $this->frameA, 'timestamp_seconds' => 1.5, 'score' => 0.88, 'reasons' => ['possible collision or near-miss risk']],
                    ['path' => $this->frameB, 'timestamp_seconds' => 2.0, 'score' => 0.81, 'reasons' => ['possible unsafe lane change']],
                ],
                temporaryDirectories: [],
                rawResponse: ['source' => 'local_opencv_yolo', 'risk_score' => 0.88],
            );
        }
    });

    $analysis = createVideoBackedAnalysis('High risk selected dashcam frames.');

    (new AnalyzeIncidentJob($analysis->id))->handle(app(AIIncidentAnalysisService::class));

    $analysis->refresh();

    expect($analysis->status)->toBe(AIAnalysis::STATUS_COMPLETED)
        ->and($analysis->detected_events)->toContain('possible collision risk')
        ->and($analysis->raw_response['local_analysis']['risk_score'])->toBe(0.88);

    Http::assertSent(fn ($request): bool => data_get($request->data(), 'input.0.content.1.type') === 'input_image'
        && data_get($request->data(), 'input.0.content.2.type') === 'input_image'
        && data_get($request->data(), 'input.0.content.3') === null
        && data_get($request->data(), 'max_output_tokens') === 500);
});

test('malformed openai response marks analysis as failed with useful error context', function () {
    Storage::fake('public');
    configureOpenAIForTests();

    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response(['id' => 'resp_bad', 'output' => []], 200),
    ]);

    $analysis = createImageBackedAnalysis('OpenAI returns a malformed response.');

    (new AnalyzeIncidentJob($analysis->id))->handle(app(AIIncidentAnalysisService::class));

    $analysis->refresh();

    expect($analysis->status)->toBe(AIAnalysis::STATUS_FAILED)
        ->and($analysis->summary)->toBe('AI analysis failed. Manual review recommended.')
        ->and($analysis->recommendation)->toContain('Manual review recommended')
        ->and($analysis->suggested_fault_decision)->toBe(IncidentReview::FAULT_UNCLEAR)
        ->and($analysis->fault_confidence_score)->toBe(0.0)
        ->and($analysis->fault_reasoning)->toContain('no reliable fault suggestion')
        ->and($analysis->raw_response['source'])->toBe('openai_responses')
        ->and($analysis->raw_response['error']['message'])->toContain('usable structured analysis');
});

test('openai final fault language is rejected and does not update score', function () {
    Storage::fake('public');
    configureOpenAIForTests();

    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response(openAIAnalysisResponse([
            'summary' => 'Fault is confirmed from the selected dashcam frame.',
            'fault_reasoning' => 'This proves the driver is guilty.',
        ]), 200),
    ]);

    $analysis = createImageBackedAnalysis('OpenAI returns final fault wording.');
    $startingScore = $analysis->incident->driver->score()->firstOrFail()->score;

    (new AnalyzeIncidentJob($analysis->id))->handle(app(AIIncidentAnalysisService::class));

    $analysis->refresh();

    expect($analysis->status)->toBe(AIAnalysis::STATUS_FAILED)
        ->and($analysis->suggested_fault_decision)->toBe(IncidentReview::FAULT_UNCLEAR)
        ->and($analysis->incident->fresh()->status)->toBe(Incident::STATUS_PENDING)
        ->and($analysis->incident->driver->score()->firstOrFail()->score)->toBe($startingScore);
});

test('missing ffmpeg marks video analysis as failed without calling openai', function () {
    Storage::fake('public');
    configureOpenAIForTests();
    config()->set('services.openai.ffmpeg_binary', 'missing-ffmpeg-binary');

    Http::fake();

    $analysis = createVideoBackedAnalysis('Dashcam video needs frame extraction.');

    (new AnalyzeIncidentJob($analysis->id))->handle(app(AIIncidentAnalysisService::class));

    $analysis->refresh();

    expect($analysis->status)->toBe(AIAnalysis::STATUS_FAILED)
        ->and($analysis->summary)->toBe('AI analysis failed. Manual review recommended.')
        ->and($analysis->raw_response['error']['message'])->toContain('FFmpeg');

    Http::assertNothingSent();
});

test('demo dashcam mode creates incident and queues analysis when enabled', function () {
    Queue::fake();
    Storage::fake('public');
    config()->set('services.dashcam.demo_mode', true);
    $demoDirectory = storage_path('framework/testing/demo-videos');
    File::ensureDirectoryExists($demoDirectory);
    File::put($demoDirectory.DIRECTORY_SEPARATOR.'sample-dashcam.mp4', 'demo-video');
    config()->set('services.dashcam.demo_video_path', $demoDirectory);

    $admin = createUserWithRole('admin');
    [, $driver, $vehicle] = createAiAnalysisDriverContext();

    $this->actingAs($admin)
        ->get(route('incident-reviews.index', ['tab' => 'ai']))
        ->assertSuccessful()
        ->assertSeeText('AI Processing')
        ->assertDontSeeText('Analyze a sample dashcam video')
        ->assertDontSeeText('sample-dashcam.mp4');

    $this->actingAs($admin)
        ->post(route('ai-analyses.demo.store'), [
            'video' => 'sample-dashcam.mp4',
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'type' => Incident::TYPE_UNSAFE_DRIVING,
        ])
        ->assertRedirect();

    $incident = Incident::query()
        ->where('description', 'Demo dashcam video analysis: sample-dashcam.mp4')
        ->with('aiAnalyses', 'media')
        ->firstOrFail();

    expect($incident->aiAnalyses)->toHaveCount(1)
        ->and($incident->media)->toHaveCount(1)
        ->and($incident->media->first()->sha256_hash)->not->toBeNull();

    Queue::assertPushed(AnalyzeIncidentJob::class);

    File::deleteDirectory($demoDirectory);
});

test('admin and monitor can view ai processing through review center but driver cannot', function () {
    $admin = createUserWithRole('admin');
    $monitor = createUserWithRole('monitor');
    $driver = createUserWithRole('driver');
    createMediaBackedAnalysis('Module visibility analysis incident.');

    $this->actingAs($admin)
        ->get(route('ai-analyses.index'))
        ->assertRedirect(route('incident-reviews.index', ['tab' => 'ai', 'status' => 'active']));

    $this->actingAs($admin)
        ->get(route('incident-reviews.index', ['tab' => 'ai']))
        ->assertSuccessful()
        ->assertSeeText('Review Center')
        ->assertSeeText('AI Processing')
        ->assertSeeText('Module visibility analysis incident.');

    $this->actingAs($monitor)
        ->get(route('dashboard.monitor'))
        ->assertSuccessful()
        ->assertSeeText('Review Center')
        ->assertDontSeeText('AI Analyses');

    $this->actingAs($driver)
        ->get(route('dashboard.driver'))
        ->assertSuccessful()
        ->assertDontSeeText('Review Center')
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
        ->assertSeeText('AI suggested fault')
        ->assertSeeText('Unclear')
        ->assertSeeText('Fault confidence')
        ->assertSeeText('0.42')
        ->assertSeeText('AI observations are advisory only');

    $this->actingAs($otherDriverUser)
        ->get(route('incidents.show', $incident))
        ->assertForbidden();
});

test('monitor review dropdown shows score impact for every fault decision', function () {
    $monitor = createUserWithRole('monitor');
    [$driverUser, $driver] = createAiAnalysisDriverContext();
    $incident = Incident::factory()->create([
        'driver_id' => $driver->id,
        'reported_by' => $driverUser->id,
        'description' => 'Dropdown AI suggestion score incident.',
        'type' => Incident::TYPE_CRASH,
        'status' => Incident::STATUS_PENDING,
    ]);
    $analysis = AIAnalysis::factory()->completed()->create(['incident_id' => $incident->id]);

    $this->actingAs($monitor)
        ->get(route('incidents.show', $incident))
        ->assertSuccessful()
        ->assertSee('<select name="fault_decision" required>', false)
        ->assertSeeText('Driver fault (-20)')
        ->assertSeeText('Other party fault (0)')
        ->assertSeeText('Shared fault (-10)')
        ->assertSeeText('Unclear (0)')
        ->assertDontSeeText('Unclear (0.42)');

    $this->actingAs($monitor)
        ->getJson(route('incidents.ai-analysis.status', $incident))
        ->assertSuccessful()
        ->assertJsonPath('suggested_fault_label', 'Unclear')
        ->assertJsonPath('fault_confidence_score', $analysis->fault_confidence_score);
});

test('monitor review dropdown uses score impact even when ai fault confidence is missing', function () {
    $monitor = createUserWithRole('monitor');
    [$driverUser, $driver] = createAiAnalysisDriverContext();
    $incident = Incident::factory()->create([
        'driver_id' => $driver->id,
        'reported_by' => $driverUser->id,
        'description' => 'Missing dropdown AI suggestion score incident.',
        'type' => Incident::TYPE_CRASH,
        'status' => Incident::STATUS_PENDING,
    ]);
    AIAnalysis::factory()->completed()->create([
        'incident_id' => $incident->id,
        'suggested_fault_decision' => IncidentReview::FAULT_DRIVER,
        'fault_confidence_score' => null,
    ]);

    $this->actingAs($monitor)
        ->get(route('incidents.show', $incident))
        ->assertSuccessful()
        ->assertSeeText('Fault confidence')
        ->assertSeeText('Pending')
        ->assertSeeText('Driver fault (-20)')
        ->assertSeeText('Shared fault (-10)');
});

test('monitor final review form defaults to active ai suggestion and reasoning', function () {
    $monitor = createUserWithRole('monitor');
    [$driverUser, $driver] = createAiAnalysisDriverContext();
    $incident = Incident::factory()->create([
        'driver_id' => $driver->id,
        'reported_by' => $driverUser->id,
        'description' => 'Autofill AI suggestion review incident.',
        'type' => Incident::TYPE_CRASH,
        'status' => Incident::STATUS_PENDING,
    ]);
    AIAnalysis::factory()->completed()->create([
        'incident_id' => $incident->id,
        'suggested_fault_decision' => IncidentReview::FAULT_SHARED,
        'fault_reasoning' => 'AI reasoning suggests shared responsibility, pending human confirmation.',
    ]);

    $this->actingAs($monitor)
        ->get(route('incidents.show', $incident))
        ->assertSuccessful()
        ->assertSee('<option value="shared_fault" selected>Shared fault (-10)</option>', false)
        ->assertSee('AI reasoning suggests shared responsibility, pending human confirmation.');
});

test('monitor final review form falls back to ai summary when reasoning is missing', function () {
    $monitor = createUserWithRole('monitor');
    [$driverUser, $driver] = createAiAnalysisDriverContext();
    $incident = Incident::factory()->create([
        'driver_id' => $driver->id,
        'reported_by' => $driverUser->id,
        'description' => 'Autofill AI summary review incident.',
        'type' => Incident::TYPE_CRASH,
        'status' => Incident::STATUS_PENDING,
    ]);
    AIAnalysis::factory()->completed()->create([
        'incident_id' => $incident->id,
        'fault_reasoning' => null,
        'summary' => 'AI summary fallback for the monitor review notes.',
    ]);

    $this->actingAs($monitor)
        ->get(route('incidents.show', $incident))
        ->assertSuccessful()
        ->assertSee('AI summary fallback for the monitor review notes.');
});

test('monitor final review form keeps old input over ai defaults after validation error', function () {
    $monitor = createUserWithRole('monitor');
    [$driverUser, $driver] = createAiAnalysisDriverContext();
    $incident = Incident::factory()->create([
        'driver_id' => $driver->id,
        'reported_by' => $driverUser->id,
        'description' => 'Autofill old input review incident.',
        'type' => Incident::TYPE_CRASH,
        'status' => Incident::STATUS_PENDING,
    ]);
    AIAnalysis::factory()->completed()->create([
        'incident_id' => $incident->id,
        'suggested_fault_decision' => IncidentReview::FAULT_SHARED,
        'fault_reasoning' => 'AI default reasoning should not overwrite old input.',
    ]);

    $this->actingAs($monitor)
        ->from(route('incidents.show', $incident))
        ->post(route('incidents.reviews.store', $incident), [
            'fault_decision' => IncidentReview::FAULT_DRIVER,
            'notes' => '',
        ])
        ->assertRedirect(route('incidents.show', $incident));

    $this->actingAs($monitor)
        ->get(route('incidents.show', $incident))
        ->assertSuccessful()
        ->assertSee('<option value="driver_fault" selected>Driver fault (-20)</option>', false)
        ->assertDontSee('<option value="shared_fault" selected>Shared fault (-10)</option>', false)
        ->assertSee('<textarea name="notes" required></textarea>', false);
});

test('ai analysis status endpoint is authorized through incident ownership', function () {
    [$driverUser, $driver] = createAiAnalysisDriverContext();
    [$otherDriverUser] = createAiAnalysisDriverContext();
    $monitor = createUserWithRole('monitor');
    $incident = Incident::factory()->create([
        'driver_id' => $driver->id,
        'reported_by' => $driverUser->id,
        'description' => 'Pollable processing incident.',
    ]);
    AIAnalysis::factory()->create([
        'incident_id' => $incident->id,
        'status' => AIAnalysis::STATUS_PROCESSING,
    ]);

    $this->actingAs($driverUser)
        ->getJson(route('incidents.ai-analysis.status', $incident))
        ->assertSuccessful()
        ->assertJsonPath('has_analysis', true)
        ->assertJsonPath('status', AIAnalysis::STATUS_PROCESSING)
        ->assertJsonPath('status_label', 'Processing')
        ->assertJsonPath('is_terminal', false);

    $this->actingAs($monitor)
        ->getJson(route('incidents.ai-analysis.status', $incident))
        ->assertSuccessful()
        ->assertJsonPath('status', AIAnalysis::STATUS_PROCESSING);

    $this->actingAs($otherDriverUser)
        ->getJson(route('incidents.ai-analysis.status', $incident))
        ->assertForbidden();
});

test('incident detail shows ai workflow timeline for processing analyses', function () {
    $monitor = createUserWithRole('monitor');
    $analysis = createMediaBackedAnalysis('Processing timeline visible incident.');
    $analysis->update(['status' => AIAnalysis::STATUS_PROCESSING]);

    $this->actingAs($monitor)
        ->get(route('incidents.show', $analysis->incident))
        ->assertSuccessful()
        ->assertSee('data-ai-analysis-panel', false)
        ->assertSee('data-ai-status-url', false)
        ->assertSeeText('Uploaded')
        ->assertSeeText('Processing')
        ->assertSeeText('AI analyzing')
        ->assertSeeText('AI processing is still running.')
        ->assertDontSeeText('AI observations are not ready yet.');
});

test('ai analysis ui has no fake generation controls', function () {
    $monitor = createUserWithRole('monitor');
    $analysis = createMediaBackedAnalysis('Read only AI analysis incident.');
    $analysis->update([
        'summary' => 'The selected dashcam frames appear to show possible unsafe behavior needing review.',
        'confidence_score' => 0.72,
        'status' => AIAnalysis::STATUS_COMPLETED,
    ]);

    $this->actingAs($monitor)
        ->get(route('incident-reviews.index', ['tab' => 'ai']))
        ->assertSuccessful()
        ->assertSee('aria-label="View incident"', false)
        ->assertSeeText('Completed')
        ->assertSeeText('0.72')
        ->assertSeeText('The selected dashcam frames appear...')
        ->assertSee('title="The selected dashcam frames appear to show possible unsafe behavior needing review."', false)
        ->assertDontSeeText('Generate fake')
        ->assertDontSeeText('View incident')
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
        ->get(route('incident-reviews.index', ['tab' => 'ai']))
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

function createImageBackedAnalysis(string $description): AIAnalysis
{
    [$driverUser, $driver, $vehicle] = createAiAnalysisDriverContext();
    $path = 'incident-media/dashcam.jpg';

    Storage::disk('public')->put($path, 'fake-image-binary');

    $incident = Incident::factory()->create([
        'driver_id' => $driver->id,
        'vehicle_id' => $vehicle->id,
        'reported_by' => $driverUser->id,
        'type' => Incident::TYPE_UNSAFE_DRIVING,
        'description' => $description,
    ]);

    IncidentMedia::factory()->create([
        'incident_id' => $incident->id,
        'file_path' => $path,
        'file_type' => IncidentMedia::TYPE_IMAGE,
        'mime_type' => 'image/jpeg',
        'original_name' => 'dashcam.jpg',
        'size' => 25_000,
    ]);

    return AIAnalysis::factory()->create(['incident_id' => $incident->id]);
}

function createVideoBackedAnalysis(string $description): AIAnalysis
{
    [$driverUser, $driver, $vehicle] = createAiAnalysisDriverContext();
    $path = 'incident-media/dashcam.mp4';

    Storage::disk('public')->put($path, 'fake-video-binary');

    $incident = Incident::factory()->create([
        'driver_id' => $driver->id,
        'vehicle_id' => $vehicle->id,
        'reported_by' => $driverUser->id,
        'type' => Incident::TYPE_UNSAFE_DRIVING,
        'description' => $description,
    ]);

    IncidentMedia::factory()->create([
        'incident_id' => $incident->id,
        'file_path' => $path,
        'file_type' => IncidentMedia::TYPE_VIDEO,
        'mime_type' => 'video/mp4',
        'original_name' => 'dashcam.mp4',
        'size' => 1_500_000,
    ]);

    return AIAnalysis::factory()->create(['incident_id' => $incident->id]);
}

function configureOpenAIForTests(): void
{
    config()->set('services.openai.api_key', 'test-openai-key');
    config()->set('services.openai.model', 'gpt-5.4-mini');
    config()->set('services.openai.base_url', 'https://api.openai.com/v1');
    config()->set('services.openai.timeout', 60);
    config()->set('services.openai.frame_count', 3);
    config()->set('services.openai.image_detail', 'low');
    config()->set('services.openai.max_output_tokens', 500);
    config()->set('services.dashcam.local_analysis_enabled', false);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function openAIAnalysisResponse(array $overrides = []): array
{
    $output = array_merge([
        'summary' => 'The visual media appears to show possible unsafe driving and may indicate a need for follow-up. Manual review recommended.',
        'detected_events' => 'possible sudden braking, short following distance may indicate elevated risk',
        'confidence_score' => 0.72,
        'recommendation' => 'AI observations are advisory only. A monitor should review the incident details and uploaded media before making a final decision.',
        'suggested_fault_decision' => IncidentReview::FAULT_SHARED,
        'fault_confidence_score' => 0.64,
        'fault_reasoning' => 'The selected visual evidence appears to show possible shared responsibility indicators, but manual review is required.',
    ], $overrides);

    return [
        'id' => 'resp_test',
        'object' => 'response',
        'status' => 'completed',
        'model' => 'gpt-5.4-mini',
        'output' => [
            [
                'type' => 'message',
                'content' => [
                    [
                        'type' => 'output_text',
                        'text' => json_encode($output, JSON_THROW_ON_ERROR),
                    ],
                ],
            ],
        ],
        'usage' => [
            'input_tokens' => 100,
            'output_tokens' => 50,
            'total_tokens' => 150,
        ],
    ];
}
