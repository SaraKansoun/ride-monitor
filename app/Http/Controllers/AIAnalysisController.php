<?php

namespace App\Http\Controllers;

use App\Models\AIAnalysis;
use App\Models\User;
use App\Services\DeactivationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AIAnalysisController extends Controller
{
    public function __construct(private DeactivationService $deactivationService) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AIAnalysis::class);

        $status = $this->statusFilter($request);

        $analyses = AIAnalysis::query()
            ->with(['incident.driver.user', 'incident.vehicle'])
            ->when($status === 'active', fn ($query) => $query->active())
            ->when($status === AIAnalysis::STATUS_INACTIVE, fn ($query) => $query->inactive())
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('ai-analyses.index', [
            'analyses' => $analyses,
            'status' => $status,
        ]);
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
}
