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
        <div>
            <p class="app-kicker">Report</p>
            <h2 class="section-title">Description</h2>
        </div>
        <p class="section-copy">{{ $incident->description }}</p>
    </section>

    <section class="workspace-panel">
        <div class="admin-header">
            <div>
                <p class="app-kicker">Advisory only</p>
                <h2 class="section-title">AI analysis</h2>
            </div>
        </div>

        @if ($activeAiAnalysis)
            <dl class="detail-grid">
                <div><dt>Status</dt><dd><x-status-badge :status="$activeAiAnalysis->status" /></dd></div>
                <div><dt>Confidence score</dt><dd>{{ $activeAiAnalysis->confidence_score !== null ? number_format($activeAiAnalysis->confidence_score, 2) : 'Pending' }}</dd></div>
                <div><dt>Detected events</dt><dd>{{ $activeAiAnalysis->detected_events ?? 'Pending' }}</dd></div>
                <div><dt>Recommendation</dt><dd>{{ $activeAiAnalysis->recommendation ?? 'Manual review required before any decision.' }}</dd></div>
            </dl>

            @if ($activeAiAnalysis->status === \App\Models\AIAnalysis::STATUS_COMPLETED)
                <p class="section-copy">{{ $activeAiAnalysis->summary }}</p>
                <p class="section-copy">AI analysis is advisory only and does not decide legal fault. A monitor makes the final human decision.</p>
            @elseif ($activeAiAnalysis->status === \App\Models\AIAnalysis::STATUS_FAILED)
                <p class="section-copy">{{ $activeAiAnalysis->summary }}</p>
                <p class="section-copy">AI observations are unavailable. A monitor should continue with manual review.</p>
            @else
                <p class="section-copy">AI observations are not ready yet.</p>
                <p class="section-copy">AI analysis is advisory only and does not decide legal fault. A monitor makes the final human decision.</p>
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
