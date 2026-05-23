@extends('layouts.app')

@section('title', 'Incident Details')

@section('content')
    @php
        $activeAiAnalysis = $incident->activeAiAnalysis;
        $activeReview = $incident->activeReview;
        $canReview = auth()->user()?->can('create', \App\Models\IncidentReview::class) ?? false;
        $canManageMedia = (auth()->user()?->can('view incidents') ?? false)
            && (auth()->user()?->can('manage deactivations') ?? false);
        $canSubmitReview = $canReview
            && $incident->is_active
            && ! $activeReview
            && in_array($incident->status, [\App\Models\Incident::STATUS_PENDING, \App\Models\Incident::STATUS_UNDER_REVIEW], true);
    @endphp

    <section class="workspace-panel">
        <div class="admin-header">
            <div>
                <p class="app-kicker">Incidents</p>
                <h2 class="section-title">Incident #{{ $incident->id }}</h2>
            </div>
            <div class="inline-actions">
                @if ($incident->is_active)
                    @can('deactivate', $incident)
                        <form method="POST" action="{{ route('incidents.deactivate', $incident) }}" data-confirm="Deactivate this incident?">
                            @csrf
                            @method('PATCH')
                            <button type="submit">Deactivate</button>
                        </form>
                    @endcan
                @else
                    @can('reactivate', $incident)
                        <form method="POST" action="{{ route('incidents.reactivate', $incident) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit">Reactivate</button>
                        </form>
                    @endcan
                @endif
                @if ($canReview && $incident->is_active && $incident->status === \App\Models\Incident::STATUS_PENDING)
                    <form method="POST" action="{{ route('incidents.review.start', $incident) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit">Start review</button>
                    </form>
                @endif
                <a href="{{ route('incidents.index') }}">Back</a>
            </div>
        </div>

        <dl class="detail-grid">
            <div><dt>Driver</dt><dd>{{ $incident->driver->user->name }}</dd></div>
            <div><dt>Driver license</dt><dd>{{ $incident->driver->license_number }}</dd></div>
            <div><dt>Driver phone</dt><dd>{{ $incident->driver->phone ?? 'Not provided' }}</dd></div>
            <div><dt>Driver status</dt><dd><x-status-badge :status="$incident->driver->status" /></dd></div>
            <div><dt>Vehicle plate</dt><dd>{{ $incident->vehicle?->plate_number ?? 'Not selected' }}</dd></div>
            <div><dt>Vehicle model</dt><dd>{{ $incident->vehicle?->model ?? 'Not selected' }}</dd></div>
            <div><dt>Vehicle year</dt><dd>{{ $incident->vehicle?->year ?? 'Not set' }}</dd></div>
            <div><dt>Type</dt><dd>{{ str_replace('_', ' ', $incident->type) }}</dd></div>
            <div><dt>Severity</dt><dd><x-status-badge :status="$incident->severity" /></dd></div>
            <div><dt>Status</dt><dd><x-status-badge :status="$incident->status" /></dd></div>
            <div><dt>Reported by</dt><dd>{{ $incident->reporter->name }}</dd></div>
            <div><dt>Reported at</dt><dd>{{ $incident->created_at->format('Y-m-d H:i') }}</dd></div>
            @if (! $incident->is_active)
                <div><dt>Deactivated by</dt><dd>{{ $incident->deactivator?->name ?? 'Unknown' }}</dd></div>
                <div><dt>Deactivated at</dt><dd>{{ $incident->deactivated_at?->format('Y-m-d H:i') ?? 'Unknown' }}</dd></div>
            @endif
        </dl>
    </section>

    <section class="workspace-panel">
        <div class="admin-header">
            <div>
                <p class="app-kicker">Report</p>
                <h2 class="section-title">Description</h2>
            </div>
            @can('update', $incident)
                <a class="app-button app-button-muted" href="{{ route('incidents.edit', $incident) }}">Edit description</a>
            @endcan
        </div>
        <p class="section-copy">{{ $incident->description }}</p>
    </section>

    <section
        class="workspace-panel"
        @if ($activeAiAnalysis)
            data-ai-analysis-panel
            data-ai-status-url="{{ route('incidents.ai-analysis.status', $incident) }}"
            data-ai-current-status="{{ $activeAiAnalysis->status }}"
        @endif
    >
        <div class="admin-header">
            <div>
                <p class="app-kicker">Advisory only</p>
                <h2 class="section-title">AI analysis</h2>
            </div>
        </div>

        @if ($activeAiAnalysis)
            @php
                $aiStatus = $activeAiAnalysis->status;
                $suggestedFaultLabel = match ($activeAiAnalysis->suggested_fault_decision) {
                    \App\Models\IncidentReview::FAULT_DRIVER => 'Possible driver fault',
                    \App\Models\IncidentReview::FAULT_OTHER_PARTY => 'Possible other party fault',
                    \App\Models\IncidentReview::FAULT_SHARED => 'Possible shared fault',
                    \App\Models\IncidentReview::FAULT_UNCLEAR => 'Unclear',
                    default => 'Pending',
                };
                $statusMessage = match ($aiStatus) {
                    \App\Models\AIAnalysis::STATUS_PENDING => 'The dashcam upload is queued for AI processing. Start the queue worker to process waiting jobs.',
                    \App\Models\AIAnalysis::STATUS_PROCESSING => 'The dashcam media is being processed locally. Important frames and risk signals are being prepared.',
                    \App\Models\AIAnalysis::STATUS_AI_ANALYZING => 'Selected dashcam frames are being analyzed for advisory safety observations.',
                    \App\Models\AIAnalysis::STATUS_COMPLETED => 'AI observations are ready for monitor review.',
                    \App\Models\AIAnalysis::STATUS_FAILED => 'AI analysis failed. Manual review can continue without AI observations.',
                    default => 'AI analysis status is being checked.',
                };
                $errorMessage = data_get($activeAiAnalysis->raw_response, 'error.message');
                $processingStepClass = match (true) {
                    in_array($aiStatus, [\App\Models\AIAnalysis::STATUS_AI_ANALYZING, \App\Models\AIAnalysis::STATUS_COMPLETED], true) => 'is-complete',
                    $aiStatus === \App\Models\AIAnalysis::STATUS_FAILED => 'is-failed',
                    in_array($aiStatus, [\App\Models\AIAnalysis::STATUS_PENDING, \App\Models\AIAnalysis::STATUS_PROCESSING], true) => 'is-active',
                    default => '',
                };
                $analyzingStepClass = match (true) {
                    $aiStatus === \App\Models\AIAnalysis::STATUS_COMPLETED => 'is-complete',
                    $aiStatus === \App\Models\AIAnalysis::STATUS_AI_ANALYZING => 'is-active',
                    $aiStatus === \App\Models\AIAnalysis::STATUS_FAILED => 'is-failed',
                    default => '',
                };
                $finalStepClass = match ($aiStatus) {
                    \App\Models\AIAnalysis::STATUS_COMPLETED => 'is-complete',
                    \App\Models\AIAnalysis::STATUS_FAILED => 'is-failed',
                    default => '',
                };
            @endphp
            <dl class="detail-grid">
                <div><dt>Status</dt><dd><x-status-badge :status="$aiStatus" data-ai-status-badge /></dd></div>
                <div><dt>Confidence score</dt><dd>{{ $activeAiAnalysis->confidence_score !== null ? number_format($activeAiAnalysis->confidence_score, 2) : 'Pending' }}</dd></div>
                <div><dt>Detected events</dt><dd>{{ $activeAiAnalysis->detected_events ?? 'Pending' }}</dd></div>
                <div><dt>AI suggested fault</dt><dd>{{ $suggestedFaultLabel }}</dd></div>
                <div><dt>Fault confidence</dt><dd>{{ $activeAiAnalysis->fault_confidence_score !== null ? number_format($activeAiAnalysis->fault_confidence_score, 2) : 'Pending' }}</dd></div>
                <div><dt>Recommendation</dt><dd>{{ $activeAiAnalysis->recommendation ?? 'Manual review required before any decision.' }}</dd></div>
            </dl>

            <div class="ai-workflow" aria-label="AI analysis processing timeline">
                <div class="ai-workflow-step is-complete" data-ai-step="uploaded">
                    <strong>Uploaded</strong>
                    <span>Media saved</span>
                </div>
                <div class="ai-workflow-step {{ $processingStepClass }}" data-ai-step="processing">
                    <strong>Processing</strong>
                    <span>Local screening</span>
                </div>
                <div class="ai-workflow-step {{ $analyzingStepClass }}" data-ai-step="ai_analyzing">
                    <strong>AI analyzing</strong>
                    <span>Selected frames</span>
                </div>
                <div class="ai-workflow-step {{ $finalStepClass }}" data-ai-step="final">
                    <strong>Completed / Failed</strong>
                    <span>Result saved</span>
                </div>
            </div>

            <p class="ai-status-note @if ($aiStatus === \App\Models\AIAnalysis::STATUS_FAILED) is-failed @endif" data-ai-status-message>
                {{ $statusMessage }}
            </p>

            @if ($aiStatus === \App\Models\AIAnalysis::STATUS_COMPLETED)
                <p class="section-copy">{{ $activeAiAnalysis->summary }}</p>
                <p class="section-copy">{{ $activeAiAnalysis->fault_reasoning ?? 'Fault suggestion is not available yet.' }}</p>
                <p class="section-copy">AI observations are advisory only. AI suggested fault is advisory only. Final decision must be submitted by a monitor before any driver score changes.</p>
            @elseif ($aiStatus === \App\Models\AIAnalysis::STATUS_FAILED)
                <p class="section-copy">{{ $activeAiAnalysis->summary }}</p>
                @if ($errorMessage)
                    <p class="section-copy">Processing error: {{ $errorMessage }}</p>
                @endif
                <p class="section-copy">AI observations are unavailable. A monitor should continue with manual review.</p>
            @else
                <p class="section-copy">AI processing is still running. This page will update automatically when final observations are saved.</p>
                <p class="section-copy">AI observations are advisory only. AI suggested fault is advisory only. Final decision must be submitted by a monitor before any driver score changes.</p>
            @endif
        @else
            <p class="section-copy">No active AI analysis exists for this incident.</p>
        @endif
    </section>

    <section class="workspace-panel">
        <div class="admin-header">
            <div>
                <p class="app-kicker">Human decision</p>
                <h2 class="section-title">Final review</h2>
            </div>
            @if ($activeReview)
                @can('deactivate', $activeReview)
                    <form method="POST" action="{{ route('incident-reviews.deactivate', $activeReview) }}" data-confirm="Deactivate this final review?">
                        @csrf
                        @method('PATCH')
                        <button class="app-button app-button-muted" type="submit">Deactivate review</button>
                    </form>
                @endcan
            @endif
        </div>

        @if ($activeReview)
            <dl class="detail-grid">
                <div><dt>Fault decision</dt><dd>{{ str_replace('_', ' ', $activeReview->fault_decision) }}</dd></div>
                <div><dt>Reviewed by</dt><dd>{{ $activeReview->reviewer->name }}</dd></div>
                <div><dt>Reviewed at</dt><dd>{{ $activeReview->reviewed_at->format('Y-m-d H:i') }}</dd></div>
                <div><dt>Review status</dt><dd><x-status-badge status="active" /></dd></div>
            </dl>
            <p class="section-copy">{{ $activeReview->notes }}</p>
        @else
            <p class="section-copy">No final review exists yet.</p>
        @endif

        @if ($canSubmitReview)
            <form class="admin-form" method="POST" action="{{ route('incidents.reviews.store', $incident) }}">
                @csrf
                <div class="form-grid">
                    <label class="form-field">
                        Fault decision
                        <select name="fault_decision" required>
                            @foreach (\App\Models\IncidentReview::FAULT_DECISIONS as $decision)
                                <option value="{{ $decision }}" @selected(old('fault_decision') === $decision)>{{ str_replace('_', ' ', $decision) }}</option>
                            @endforeach
                        </select>
                        @error('fault_decision')
                            <span class="form-error">{{ $message }}</span>
                        @enderror
                    </label>
                </div>

                <label class="form-field">
                    Review notes
                    <textarea name="notes" required>{{ old('notes') }}</textarea>
                    @error('notes')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </label>

                <div class="form-actions">
                    <button class="app-button app-button-primary" type="submit">Submit final review</button>
                </div>
            </form>
        @endif
    </section>

    <section class="workspace-panel">
        <div class="admin-header">
            <div>
                <p class="app-kicker">Uploads</p>
                <h2 class="section-title">Media</h2>
            </div>
        </div>

        @if ($canManageMedia)
            <nav class="admin-filters" aria-label="Incident media status filters">
                @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'all' => 'All'] as $value => $label)
                    <a class="admin-filter-link @if ($mediaStatus === $value) is-active @endif" href="{{ route('incidents.show', ['incident' => $incident, 'media_status' => $value]) }}">{{ $label }}</a>
                @endforeach
            </nav>
        @endif

        @if ($incident->media->isEmpty())
            <p class="section-copy">No media uploaded.</p>
        @else
            <div class="media-grid">
                @foreach ($incident->media as $media)
                    <article class="media-item">
                        @if (! $media->is_active)
                            <div class="media-document-link">Inactive media</div>
                        @elseif ($media->file_type === \App\Models\IncidentMedia::TYPE_IMAGE)
                            <img class="media-preview" src="{{ route('incident-media.show', $media) }}" alt="{{ $media->original_name }}">
                        @elseif ($media->file_type === \App\Models\IncidentMedia::TYPE_VIDEO)
                            <video class="media-preview" src="{{ route('incident-media.show', $media) }}" controls></video>
                        @else
                            <a class="media-document-link" href="{{ route('incident-media.show', $media) }}" target="_blank" rel="noreferrer">View document</a>
                        @endif
                        <div class="media-meta">
                            <strong>{{ $media->original_name }}</strong>
                            <span>{{ $media->mime_type }} - {{ round($media->size / 1024, 1) }} KB</span>
                            <span><x-status-badge :status="$media->is_active ? 'active' : 'inactive'" /></span>
                            @if ($canManageMedia)
                                <div class="inline-actions">
                                    @if ($media->is_active)
                                        <form method="POST" action="{{ route('incident-media.deactivate', $media) }}" data-confirm="Deactivate this media item?">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit">Deactivate</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('incident-media.reactivate', $media) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit">Reactivate</button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
@endsection
