<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIncidentRequest;
use App\Http\Requests\UpdateIncidentDescriptionRequest;
use App\Jobs\AnalyzeIncidentJob;
use App\Models\AIAnalysis;
use App\Models\Driver;
use App\Models\DriverVehicle;
use App\Models\Incident;
use App\Models\IncidentMedia;
use App\Models\IncidentReview;
use App\Models\User;
use App\Services\AIAnalysisReuseService;
use App\Services\CsvExportService;
use App\Services\MediaFingerprintService;
use App\Services\PermissionCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IncidentController extends Controller
{
    public function __construct(
        private CsvExportService $csvExportService,
        private MediaFingerprintService $mediaFingerprintService,
        private AIAnalysisReuseService $aiAnalysisReuseService
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Incident::class);

        $status = $this->statusFilter($request);
        $type = $this->typeFilter($request);
        $severity = $this->severityFilter($request);
        $workflowStatus = $this->workflowStatusFilter($request);
        $reportedFrom = $this->dateFilter($request, 'reported_from');
        $reportedTo = $this->dateFilter($request, 'reported_to');
        $search = $this->searchTerm($request);
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $incidents = $this->filteredIncidents($user, $status, $type, $severity, $workflowStatus, $reportedFrom, $reportedTo, $search)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('incidents.index', [
            'incidents' => $incidents,
            'reportedFrom' => $reportedFrom,
            'reportedTo' => $reportedTo,
            'search' => $search,
            'severity' => $severity,
            'severities' => Incident::SEVERITIES,
            'status' => $status,
            'type' => $type,
            'types' => Incident::TYPES,
            'workflowStatus' => $workflowStatus,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Incident::class);

        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $status = $this->statusFilter($request);
        $type = $this->typeFilter($request);
        $severity = $this->severityFilter($request);
        $workflowStatus = $this->workflowStatusFilter($request);
        $reportedFrom = $this->dateFilter($request, 'reported_from');
        $reportedTo = $this->dateFilter($request, 'reported_to');
        $search = $this->searchTerm($request);
        $incidents = $this->filteredIncidents($user, $status, $type, $severity, $workflowStatus, $reportedFrom, $reportedTo, $search)
            ->latest()
            ->get();

        return $this->csvExportService->download(
            'incidents.csv',
            ['ID', 'Severity', 'Type', 'Status', 'Description', 'Driver', 'Vehicle', 'Media Count', 'Reported At'],
            $this->csvExportService->rows($incidents, fn (Incident $incident): array => [
                $incident->id,
                $incident->severity,
                $incident->type,
                $incident->status,
                $incident->description,
                data_get($incident, 'driver.user.name'),
                data_get($incident, 'vehicle.plate_number', 'Not selected'),
                $incident->media_count,
                $incident->created_at?->format('Y-m-d H:i'),
            ])
        );
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Incident::class);

        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $driver = $user->driverProfile()
            ->with('currentAssignments.vehicle')
            ->firstOrFail();

        return view('incidents.create', [
            'assignedVehicles' => $driver->currentAssignments
                ->map(fn (DriverVehicle $assignment) => $assignment->vehicle)
                ->filter()
                ->values(),
            'incident' => new Incident(['status' => Incident::STATUS_PENDING]),
            'types' => Incident::TYPES,
        ]);
    }

    public function store(StoreIncidentRequest $request): RedirectResponse
    {
        $user = $request->user();
        $driver = $user?->driverProfile;

        abort_unless($user instanceof User && $driver instanceof Driver, 403);

        $aiAnalysis = null;
        $hasVisualMedia = false;
        $visualMediaHashes = [];
        $mediaFiles = $request->file('media', []);
        $mediaFiles = is_array($mediaFiles) ? $mediaFiles : [];

        $incident = DB::transaction(function () use ($driver, &$hasVisualMedia, $mediaFiles, $request, &$aiAnalysis, $user, &$visualMediaHashes): Incident {
            $incident = Incident::query()->create([
                'driver_id' => $driver->id,
                'vehicle_id' => $request->validated('vehicle_id'),
                'type' => $request->validated('type'),
                'description' => $request->validated('description'),
                'status' => Incident::STATUS_PENDING,
                'reported_by' => $user->id,
                'is_active' => true,
            ]);

            foreach ($mediaFiles as $file) {
                $sha256Hash = $this->mediaFingerprintService->hashUploadedFile($file);
                $path = $file->store('incident-media', 'public');

                if ($path === false) {
                    throw new RuntimeException('Unable to store incident media.');
                }

                $mimeType = $file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream';
                $fileType = $this->fileType($mimeType);
                $hasVisualMedia = $hasVisualMedia
                    || in_array($fileType, [IncidentMedia::TYPE_IMAGE, IncidentMedia::TYPE_VIDEO], true);

                if (in_array($fileType, [IncidentMedia::TYPE_IMAGE, IncidentMedia::TYPE_VIDEO], true)) {
                    $visualMediaHashes[] = $sha256Hash;
                }

                $incident->media()->create([
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'file_type' => $fileType,
                    'mime_type' => $mimeType,
                    'size' => $file->getSize(),
                    'sha256_hash' => $sha256Hash,
                    'uploaded_by' => $user->id,
                    'is_active' => true,
                ]);
            }

            if ($hasVisualMedia) {
                $fingerprint = $this->mediaFingerprintService->fingerprintFromHashes($visualMediaHashes);

                $aiAnalysis = $incident->aiAnalyses()->create($this->aiAnalysisReuseService->attributesFor($fingerprint));
            }

            return $incident;
        });

        if ($aiAnalysis instanceof AIAnalysis && ! $aiAnalysis->isTerminal()) {
            AnalyzeIncidentJob::dispatch($aiAnalysis->id);
        }

        return redirect()->route('incidents.show', $incident)->with('status', 'Incident reported.');
    }

    public function aiAnalysisStatus(Incident $incident): JsonResponse
    {
        Gate::authorize('view', $incident);

        $incident->load('activeAiAnalysis');

        $aiAnalysis = $incident->activeAiAnalysis;

        if (! $aiAnalysis instanceof AIAnalysis) {
            return response()->json([
                'has_analysis' => false,
                'status' => null,
                'status_label' => 'No active analysis',
                'is_terminal' => true,
            ]);
        }

        return response()->json([
            'has_analysis' => true,
            'status' => $aiAnalysis->status,
            'status_label' => $this->aiStatusLabel($aiAnalysis->status),
            'is_terminal' => $aiAnalysis->isTerminal(),
            'summary' => $aiAnalysis->summary,
            'detected_events' => $aiAnalysis->detected_events,
            'confidence_score' => $aiAnalysis->confidence_score,
            'recommendation' => $aiAnalysis->recommendation,
            'suggested_fault_decision' => $aiAnalysis->suggested_fault_decision,
            'suggested_fault_label' => $this->suggestedFaultLabel($aiAnalysis),
            'fault_confidence_score' => $aiAnalysis->fault_confidence_score,
            'fault_reasoning' => $aiAnalysis->fault_reasoning,
            'error_message' => data_get($aiAnalysis->raw_response, 'error.message'),
            'updated_at' => $aiAnalysis->updated_at?->toIso8601String(),
        ]);
    }

    public function show(Request $request, Incident $incident): View
    {
        Gate::authorize('view', $incident);

        $user = $request->user();
        $mediaStatus = $user instanceof User && $user->can(PermissionCatalog::VIEW_INCIDENTS)
            ? $this->mediaStatusFilter($request)
            : 'active';

        $incident->load([
            'activeAiAnalysis',
            'activeReview.reviewer',
            'deactivator',
            'driver.user',
            'media' => fn ($query) => $query
                ->when($mediaStatus === 'active', fn ($query) => $query->active())
                ->when($mediaStatus === 'inactive', fn ($query) => $query->inactive())
                ->latest(),
            'reporter',
            'vehicle',
        ]);

        return view('incidents.show', [
            'incident' => $incident,
            'mediaStatus' => $mediaStatus,
        ]);
    }

    public function edit(Incident $incident): View
    {
        Gate::authorize('update', $incident);

        return view('incidents.edit', [
            'incident' => $incident,
        ]);
    }

    public function update(UpdateIncidentDescriptionRequest $request, Incident $incident): RedirectResponse
    {
        $incident->update($request->safe()->only(['description']));

        return redirect()->route('incidents.show', $incident)->with('status', 'Incident description updated.');
    }

    public function deactivate(Request $request, Incident $incident): RedirectResponse
    {
        Gate::authorize('deactivate', $incident);

        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $incident->update([
            'status' => Incident::STATUS_INACTIVE,
            'is_active' => false,
            'deactivated_at' => now(),
            'deactivated_by' => $user->id,
        ]);

        return back()->with('status', 'Incident deactivated.');
    }

    public function reactivate(Incident $incident): RedirectResponse
    {
        Gate::authorize('reactivate', $incident);

        $incident->update([
            'status' => Incident::STATUS_PENDING,
            'is_active' => true,
            'deactivated_at' => null,
            'deactivated_by' => null,
        ]);

        return back()->with('status', 'Incident reactivated.');
    }

    private function statusFilter(Request $request): string
    {
        $status = $request->string('status', 'active')->toString();

        return in_array($status, ['active', Incident::STATUS_INACTIVE, 'all'], true) ? $status : 'active';
    }

    private function typeFilter(Request $request): string
    {
        $type = $request->string('type', 'all')->toString();

        return in_array($type, [...Incident::TYPES, 'all'], true) ? $type : 'all';
    }

    private function severityFilter(Request $request): string
    {
        $severity = $request->string('severity', 'all')->toString();

        return in_array($severity, [...Incident::SEVERITIES, 'all'], true) ? $severity : 'all';
    }

    private function workflowStatusFilter(Request $request): string
    {
        $workflowStatus = $request->string('incident_status', 'all')->toString();

        return in_array($workflowStatus, [...Incident::STATUSES, 'all'], true) ? $workflowStatus : 'all';
    }

    private function dateFilter(Request $request, string $key): ?string
    {
        $date = $request->string($key)->toString();

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : null;
    }

    private function searchTerm(Request $request): string
    {
        return trim($request->string('q')->toString());
    }

    private function mediaStatusFilter(Request $request): string
    {
        $status = $request->string('media_status', 'active')->toString();

        return in_array($status, ['active', 'inactive', 'all'], true) ? $status : 'active';
    }

    private function fileType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return IncidentMedia::TYPE_IMAGE;
        }

        if (str_starts_with($mimeType, 'video/')) {
            return IncidentMedia::TYPE_VIDEO;
        }

        return IncidentMedia::TYPE_DOCUMENT;
    }

    private function aiStatusLabel(?string $status): string
    {
        return match ($status) {
            AIAnalysis::STATUS_PENDING => 'Queued',
            AIAnalysis::STATUS_PROCESSING => 'Processing',
            AIAnalysis::STATUS_AI_ANALYZING => 'AI analyzing',
            AIAnalysis::STATUS_COMPLETED => 'Completed',
            AIAnalysis::STATUS_FAILED => 'Failed',
            AIAnalysis::STATUS_INACTIVE => 'Inactive',
            default => 'Unknown',
        };
    }

    private function suggestedFaultLabel(AIAnalysis $aiAnalysis): string
    {
        return IncidentReview::faultDecisionLabel($aiAnalysis->suggested_fault_decision);
    }

    /**
     * @return Builder<Incident>
     */
    private function filteredIncidents(
        User $user,
        string $status,
        string $type,
        string $severity,
        string $workflowStatus,
        ?string $reportedFrom,
        ?string $reportedTo,
        string $search
    ): Builder {
        return Incident::query()
            ->with(['driver.user', 'vehicle', 'reporter'])
            ->withCount(['media' => fn (Builder $query) => $query->where('is_active', true)])
            ->when(! $user->can(PermissionCatalog::VIEW_INCIDENTS), function (Builder $query) use ($user): void {
                $driver = $user->driverProfile;

                $query->where('driver_id', $driver instanceof Driver ? $driver->id : 0);
            })
            ->when($status === 'active', fn (Builder $query) => $query->active())
            ->when($status === Incident::STATUS_INACTIVE, fn (Builder $query) => $query->inactive())
            ->when($type !== 'all', fn (Builder $query) => $query->where('type', $type))
            ->when($severity !== 'all', fn (Builder $query) => $query->where('severity', $severity))
            ->when($workflowStatus !== 'all', fn (Builder $query) => $query->where('status', $workflowStatus))
            ->when($reportedFrom !== null, fn (Builder $query) => $query->whereDate('created_at', '>=', $reportedFrom))
            ->when($reportedTo !== null, fn (Builder $query) => $query->whereDate('created_at', '<=', $reportedTo))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('description', 'like', "%{$search}%")
                        ->orWhereHas('driver.user', function (Builder $query) use ($search): void {
                            $query
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('vehicle', function (Builder $query) use ($search): void {
                            $query
                                ->where('plate_number', 'like', "%{$search}%")
                                ->orWhere('model', 'like', "%{$search}%");
                        });
                });
            });
    }
}
