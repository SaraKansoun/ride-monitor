<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIncidentRequest;
use App\Jobs\AnalyzeIncidentJob;
use App\Models\AIAnalysis;
use App\Models\Driver;
use App\Models\DriverVehicle;
use App\Models\Incident;
use App\Models\IncidentMedia;
use App\Models\User;
use App\Services\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

class IncidentController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Incident::class);

        $status = $this->statusFilter($request);
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $incidents = Incident::query()
            ->with(['driver.user', 'vehicle', 'reporter'])
            ->withCount(['media' => fn ($query) => $query->where('is_active', true)])
            ->when(! $user->can(PermissionCatalog::VIEW_INCIDENTS), function ($query) use ($user): void {
                $driver = $user->driverProfile;

                $query->where('driver_id', $driver instanceof Driver ? $driver->id : 0);
            })
            ->when($status === 'active', fn ($query) => $query->active())
            ->when($status === Incident::STATUS_INACTIVE, fn ($query) => $query->inactive())
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('incidents.index', [
            'incidents' => $incidents,
            'status' => $status,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Incident::class);

        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $driver = $user->driverProfile()
            ->with('currentAssignments.vehicle')
            ->firstOrFail();

        abort_unless($driver instanceof Driver, 404);

        return view('incidents.create', [
            'assignedVehicles' => $driver->currentAssignments
                ->map(fn ($assignment) => $assignment instanceof DriverVehicle ? $assignment->vehicle : null)
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
        $mediaFiles = $request->file('media', []);
        $mediaFiles = is_array($mediaFiles) ? $mediaFiles : [];

        $incident = DB::transaction(function () use ($driver, $mediaFiles, $request, &$aiAnalysis, $user): Incident {
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
                $path = $file->store('incident-media', 'public');

                if ($path === false) {
                    throw new RuntimeException('Unable to store incident media.');
                }

                $mimeType = $file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream';

                $incident->media()->create([
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'file_type' => $this->fileType($mimeType),
                    'mime_type' => $mimeType,
                    'size' => $file->getSize(),
                    'uploaded_by' => $user->id,
                    'is_active' => true,
                ]);
            }

            if ($mediaFiles !== []) {
                $aiAnalysis = $incident->aiAnalyses()->create([
                    'status' => AIAnalysis::STATUS_PENDING,
                    'is_active' => true,
                ]);
            }

            return $incident;
        });

        if ($aiAnalysis instanceof AIAnalysis) {
            AnalyzeIncidentJob::dispatch($aiAnalysis->id);
        }

        return redirect()->route('incidents.show', $incident)->with('status', 'Incident reported.');
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
}
