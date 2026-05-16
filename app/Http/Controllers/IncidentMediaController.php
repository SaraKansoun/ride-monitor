<?php

namespace App\Http\Controllers;

use App\Models\IncidentMedia;
use App\Models\User;
use App\Services\DeactivationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class IncidentMediaController extends Controller
{
    public function __construct(private DeactivationService $deactivationService) {}

    public function show(IncidentMedia $incidentMedia): BinaryFileResponse
    {
        abort_unless($incidentMedia->is_active, 404);

        $incidentMedia->load('incident');

        Gate::authorize('view', $incidentMedia);

        $path = Storage::disk('public')->path($incidentMedia->file_path);

        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => $incidentMedia->mime_type,
            'Content-Disposition' => 'inline; filename="'.$incidentMedia->original_name.'"',
        ]);
    }

    public function deactivate(Request $request, IncidentMedia $incidentMedia): RedirectResponse
    {
        Gate::authorize('deactivate', $incidentMedia);

        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $this->deactivationService->deactivateIncidentMedia($incidentMedia, $user);

        return back()->with('status', 'Incident media deactivated.');
    }

    public function reactivate(IncidentMedia $incidentMedia): RedirectResponse
    {
        Gate::authorize('reactivate', $incidentMedia);

        $this->deactivationService->reactivateIncidentMedia($incidentMedia);

        return back()->with('status', 'Incident media reactivated.');
    }
}
