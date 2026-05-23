<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIncidentReviewRequest;
use App\Models\AIAnalysis;
use App\Models\Driver;
use App\Models\Incident;
use App\Models\IncidentReview;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DriverScoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class IncidentReviewController extends Controller
{
    public function __construct(private DriverScoreService $driverScoreService) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', IncidentReview::class);

        $tab = $this->tabFilter($request);
        $reviewStatus = $this->reviewStatusFilter($request);
        $aiStatus = $this->aiStatusFilter($request);

        $aiAnalyses = null;
        $pendingIncidents = null;
        $reviews = null;

        if ($tab === 'pending') {
            $pendingIncidents = Incident::query()
                ->active()
                ->whereIn('status', [Incident::STATUS_PENDING, Incident::STATUS_UNDER_REVIEW])
                ->whereDoesntHave('activeReview')
                ->with(['driver.user', 'vehicle'])
                ->withCount(['media' => fn ($query) => $query->where('is_active', true)])
                ->latest()
                ->paginate(15)
                ->withQueryString();
        } elseif ($tab === 'reviews') {
            $reviews = IncidentReview::query()
                ->with(['incident.driver.user', 'incident.vehicle', 'reviewer'])
                ->when($reviewStatus === 'active', fn ($query) => $query->active())
                ->when($reviewStatus === 'inactive', fn ($query) => $query->inactive())
                ->latest('reviewed_at')
                ->paginate(15)
                ->withQueryString();
        } else {
            Gate::authorize('viewAny', AIAnalysis::class);

            $aiAnalyses = AIAnalysis::query()
                ->with(['incident.activeReview', 'incident.driver.user', 'incident.vehicle'])
                ->when($aiStatus === 'active', fn ($query) => $query->active())
                ->when($aiStatus === AIAnalysis::STATUS_INACTIVE, fn ($query) => $query->inactive())
                ->latest()
                ->paginate(15)
                ->withQueryString();
        }

        return view('incident-reviews.index', [
            'aiAnalyses' => $aiAnalyses,
            'aiStatus' => $aiStatus,
            'demoDrivers' => $tab === 'ai' && $this->demoMode() ? Driver::query()->active()->with('user')->orderBy('id')->get() : collect(),
            'demoMode' => $tab === 'ai' && $this->demoMode(),
            'demoTypes' => Incident::TYPES,
            'demoVehicles' => $tab === 'ai' && $this->demoMode() ? Vehicle::query()->active()->orderBy('plate_number')->get() : collect(),
            'demoVideos' => $tab === 'ai' && $this->demoMode() ? $this->demoVideos() : [],
            'pendingIncidents' => $pendingIncidents,
            'reviewStatus' => $reviewStatus,
            'reviews' => $reviews,
            'tab' => $tab,
        ]);
    }

    public function start(Incident $incident): RedirectResponse
    {
        Gate::authorize('create', IncidentReview::class);

        if (! $incident->isActive()) {
            return back()->withErrors(['incident' => 'Inactive incidents cannot be reviewed.']);
        }

        if ($incident->activeReview()->exists()) {
            return back()->withErrors(['incident' => 'This incident already has an active final review.']);
        }

        if ($incident->status === Incident::STATUS_PENDING) {
            $incident->update(['status' => Incident::STATUS_UNDER_REVIEW]);
        }

        return redirect()->route('incidents.show', $incident)->with('status', 'Incident moved under review.');
    }

    public function store(StoreIncidentReviewRequest $request, Incident $incident): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        DB::transaction(function () use ($incident, $request, $user): void {
            $incident->reviews()->create([
                'reviewed_by' => $user->id,
                'fault_decision' => $request->validated('fault_decision'),
                'notes' => $request->validated('notes'),
                'reviewed_at' => now(),
                'is_active' => true,
            ]);

            $incident->update(['status' => Incident::STATUS_RESOLVED]);
            $this->driverScoreService->recalculateForIncident($incident);
        });

        return redirect()->route('incidents.show', $incident)->with('status', 'Final review submitted.');
    }

    public function deactivate(Request $request, IncidentReview $incidentReview): RedirectResponse
    {
        Gate::authorize('deactivate', $incidentReview);

        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $incident = $incidentReview->incident;

        abort_unless($incident instanceof Incident, 404);

        DB::transaction(function () use ($incident, $incidentReview, $user): void {
            $incidentReview->update([
                'is_active' => false,
                'deactivated_at' => now(),
                'deactivated_by' => $user->id,
            ]);

            $incident->update(['status' => Incident::STATUS_UNDER_REVIEW]);
            $this->driverScoreService->recalculateForIncident($incident);
        });

        return back()->with('status', 'Review deactivated.');
    }

    public function reactivate(IncidentReview $incidentReview): RedirectResponse
    {
        Gate::authorize('reactivate', $incidentReview);

        $incident = $incidentReview->incident;

        abort_unless($incident instanceof Incident, 404);

        if ($incident->activeReview()->whereKeyNot($incidentReview->id)->exists()) {
            return back()->withErrors(['review' => 'This incident already has another active review.']);
        }

        DB::transaction(function () use ($incident, $incidentReview): void {
            $incidentReview->update([
                'is_active' => true,
                'deactivated_at' => null,
                'deactivated_by' => null,
            ]);

            $incident->update(['status' => Incident::STATUS_RESOLVED]);
            $this->driverScoreService->recalculateForIncident($incident);
        });

        return back()->with('status', 'Review reactivated.');
    }

    private function tabFilter(Request $request): string
    {
        $tab = $request->string('tab', 'pending')->toString();

        return in_array($tab, ['pending', 'reviews', 'ai'], true) ? $tab : 'pending';
    }

    private function reviewStatusFilter(Request $request): string
    {
        $status = $request->string('status', 'active')->toString();

        return in_array($status, ['active', 'inactive', 'all'], true) ? $status : 'active';
    }

    private function aiStatusFilter(Request $request): string
    {
        $status = $request->string('status', 'active')->toString();

        return in_array($status, ['active', AIAnalysis::STATUS_INACTIVE, 'all'], true) ? $status : 'active';
    }

    private function demoMode(): bool
    {
        return (bool) config('services.dashcam.demo_mode', true);
    }

    /**
     * @return list<string>
     */
    private function demoVideos(): array
    {
        $directory = (string) config('services.dashcam.demo_video_path', storage_path('app/demo-videos'));

        if (! is_dir($directory)) {
            return [];
        }

        return collect(File::files($directory))
            ->filter(fn ($file): bool => in_array(strtolower($file->getExtension()), ['mp4', 'mov', 'avi'], true))
            ->map(fn ($file): string => $file->getFilename())
            ->values()
            ->all();
    }
}
