<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeIncidentJob;
use App\Models\AIAnalysis;
use App\Models\Driver;
use App\Models\Incident;
use App\Models\IncidentMedia;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AIAnalysisReuseService;
use App\Services\DeactivationService;
use App\Services\MediaFingerprintService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AIAnalysisController extends Controller
{
    public function __construct(
        private DeactivationService $deactivationService,
        private MediaFingerprintService $mediaFingerprintService,
        private AIAnalysisReuseService $aiAnalysisReuseService
    ) {}

    public function index(Request $request): RedirectResponse
    {
        Gate::authorize('viewAny', AIAnalysis::class);

        $status = $this->statusFilter($request);

        return redirect()->route('incident-reviews.index', [
            'tab' => 'ai',
            'status' => $status,
        ]);
    }

    public function storeDemo(Request $request): RedirectResponse
    {
        Gate::authorize('viewAny', AIAnalysis::class);
        abort_unless($this->demoMode(), 404);

        $validated = $request->validate([
            'video' => ['required', 'string'],
            'driver_id' => [
                'required',
                'integer',
                Rule::exists((new Driver)->getTable(), 'id')
                    ->where(fn (Builder $query) => $query->where('is_active', true)),
            ],
            'vehicle_id' => [
                'nullable',
                'integer',
                Rule::exists((new Vehicle)->getTable(), 'id')
                    ->where(fn (Builder $query) => $query->where('is_active', true)),
            ],
            'type' => ['required', Rule::in(Incident::TYPES)],
        ]);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $sourcePath = $this->demoVideoPath((string) $validated['video']);
        $hash = $this->mediaFingerprintService->hashFile($sourcePath);
        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'mp4';
        $storedPath = 'incident-media/demo-'.Str::uuid().'.'.$extension;
        $stored = Storage::disk('public')->put($storedPath, File::get($sourcePath));

        abort_unless($stored, 500, 'Unable to copy demo dashcam video.');

        $aiAnalysis = null;
        $incident = DB::transaction(function () use ($hash, &$aiAnalysis, $storedPath, $sourcePath, $user, $validated): Incident {
            $incident = Incident::query()->create([
                'driver_id' => $validated['driver_id'],
                'vehicle_id' => $validated['vehicle_id'] ?? null,
                'type' => $validated['type'],
                'description' => 'Demo dashcam video analysis: '.basename($sourcePath),
                'status' => Incident::STATUS_PENDING,
                'reported_by' => $user->id,
                'is_active' => true,
            ]);

            $incident->media()->create([
                'file_path' => $storedPath,
                'original_name' => basename($sourcePath),
                'file_type' => IncidentMedia::TYPE_VIDEO,
                'mime_type' => 'video/mp4',
                'size' => filesize($sourcePath) ?: 0,
                'sha256_hash' => $hash,
                'uploaded_by' => $user->id,
                'is_active' => true,
            ]);

            $fingerprint = $this->mediaFingerprintService->fingerprintFromHashes([$hash]);
            $aiAnalysis = $incident->aiAnalyses()->create($this->aiAnalysisReuseService->attributesFor($fingerprint));

            return $incident;
        });

        if ($aiAnalysis instanceof AIAnalysis && ! $aiAnalysis->isTerminal()) {
            AnalyzeIncidentJob::dispatch($aiAnalysis->id);
        }

        return redirect()
            ->route('incidents.show', $incident)
            ->with('status', 'Demo dashcam incident queued for analysis.');
    }

    public function deactivate(Request $request, AIAnalysis $aiAnalysis): RedirectResponse
    {
        Gate::authorize('deactivate', $aiAnalysis);

        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $this->deactivationService->deactivateAIAnalysis($aiAnalysis, $user);

        return back()->with('status', 'AI analysis deactivated.');
    }

    public function reactivate(AIAnalysis $aiAnalysis): RedirectResponse
    {
        Gate::authorize('reactivate', $aiAnalysis);

        $this->deactivationService->reactivateAIAnalysis($aiAnalysis);

        return back()->with('status', 'AI analysis reactivated.');
    }

    private function statusFilter(Request $request): string
    {
        $status = $request->string('status', 'active')->toString();

        return in_array($status, ['active', AIAnalysis::STATUS_INACTIVE, 'all'], true) ? $status : 'active';
    }

    private function demoMode(): bool
    {
        return (bool) config('services.dashcam.demo_mode', true);
    }

    private function demoVideoPath(string $filename): string
    {
        $directory = realpath((string) config('services.dashcam.demo_video_path', storage_path('app/demo-videos')));
        $path = $directory === false ? false : realpath($directory.DIRECTORY_SEPARATOR.basename($filename));

        abort_if($directory === false || $path === false || ! str_starts_with($path, $directory), 404);

        return $path;
    }
}
