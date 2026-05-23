@extends('layouts.app')

@section('title', 'Review Center')

@section('content')
    <section class="admin-header">
        <div>
            <p class="app-kicker">Monitor</p>
            <h2 class="section-title">Review Center</h2>
        </div>
    </section>

    <nav class="admin-filters" aria-label="Incident review tabs">
        <a class="admin-filter-link @if ($tab === 'pending') is-active @endif" href="{{ route('incident-reviews.index', ['tab' => 'pending']) }}">Pending Reviews</a>
        <a class="admin-filter-link @if ($tab === 'reviews') is-active @endif" href="{{ route('incident-reviews.index', ['tab' => 'reviews']) }}">Completed Reviews</a>
        <a class="admin-filter-link @if ($tab === 'ai') is-active @endif" href="{{ route('incident-reviews.index', ['tab' => 'ai']) }}">AI Processing</a>
    </nav>

    @if ($tab === 'pending')
        <section class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Incident</th>
                        <th>Description</th>
                        <th>Driver</th>
                        <th>Vehicle</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Media</th>
                        <th>Reported</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pendingIncidents as $incident)
                        <tr>
                            <td>#{{ $incident->id }}</td>
                            <td><x-compact-text :text="$incident->description" /></td>
                            <td>{{ $incident->driver->user->name }}</td>
                            <td>{{ $incident->vehicle?->plate_number ?? 'Not selected' }}</td>
                            <td>{{ str_replace('_', ' ', $incident->type) }}</td>
                            <td><x-status-badge :status="$incident->status" /></td>
                            <td>{{ $incident->media_count }}</td>
                            <td>{{ $incident->created_at->format('Y-m-d H:i') }}</td>
                            <td>
                                <div class="inline-actions">
                                    <x-action-icon name="open" label="Open incident" :href="route('incidents.show', $incident)" />
                                    @if ($incident->status === \App\Models\Incident::STATUS_PENDING)
                                        <form method="POST" action="{{ route('incidents.review.start', $incident) }}">
                                            @csrf
                                            @method('PATCH')
                                            <x-action-icon name="start" label="Start review" type="submit" />
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">No pending reviews found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <div class="admin-pagination">{{ $pendingIncidents->links() }}</div>
    @elseif ($tab === 'reviews')
        <nav class="admin-filters" aria-label="Review status filters">
            @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'all' => 'All'] as $value => $label)
                <a class="admin-filter-link @if ($reviewStatus === $value) is-active @endif" href="{{ route('incident-reviews.index', ['tab' => 'reviews', 'status' => $value]) }}">{{ $label }}</a>
            @endforeach
        </nav>

        <section class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Incident</th>
                        <th>Driver</th>
                        <th>Vehicle</th>
                        <th>Decision</th>
                        <th>Status</th>
                        <th>Reviewed by</th>
                        <th>Reviewed</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reviews as $review)
                        <tr>
                            <td>#{{ $review->incident->id }}</td>
                            <td>{{ $review->incident->driver->user->name }}</td>
                            <td>{{ $review->incident->vehicle?->plate_number ?? 'Not selected' }}</td>
                            <td>{{ str_replace('_', ' ', $review->fault_decision) }}</td>
                            <td><x-status-badge :status="$review->is_active ? 'active' : 'inactive'" /></td>
                            <td>{{ $review->reviewer->name }}</td>
                            <td>{{ $review->reviewed_at->format('Y-m-d H:i') }}</td>
                            <td>
                                <div class="inline-actions">
                                    <x-action-icon name="view" label="View incident" :href="route('incidents.show', $review->incident)" />
                                    @if ($review->is_active)
                                        @can('deactivate', $review)
                                            <form method="POST" action="{{ route('incident-reviews.deactivate', $review) }}" data-confirm="Deactivate this final review?">
                                                @csrf
                                                @method('PATCH')
                                                <x-action-icon name="deactivate" label="Deactivate review" type="submit" />
                                            </form>
                                        @endcan
                                    @else
                                        @can('reactivate', $review)
                                            <form method="POST" action="{{ route('incident-reviews.reactivate', $review) }}">
                                                @csrf
                                                @method('PATCH')
                                                <x-action-icon name="reactivate" label="Reactivate review" type="submit" />
                                            </form>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">No incident reviews found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <div class="admin-pagination">{{ $reviews->links() }}</div>
    @else
        @if ($demoMode)
            <section class="workspace-panel">
                <div class="admin-header">
                    <div>
                        <p class="app-kicker">Demo testing mode</p>
                        <h3 class="section-title">Analyze a sample dashcam video</h3>
                        <p class="section-copy">Place demo videos in <code>storage/app/demo-videos</code>, then create a demo incident from one of the samples.</p>
                    </div>
                </div>

                @if ($demoVideos === [])
                    <p class="section-copy">No demo dashcam videos found yet.</p>
                @else
                    <form class="admin-form" method="POST" action="{{ route('ai-analyses.demo.store') }}">
                        @csrf
                        <div class="form-grid">
                            <label class="form-field">
                                <span>Sample video</span>
                                <select name="video" required>
                                    @foreach ($demoVideos as $video)
                                        <option value="{{ $video }}">{{ $video }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="form-field">
                                <span>Driver</span>
                                <select name="driver_id" required>
                                    @foreach ($demoDrivers as $driver)
                                        <option value="{{ $driver->id }}">{{ $driver->user->name }} - {{ $driver->license_number }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="form-field">
                                <span>Vehicle</span>
                                <select name="vehicle_id">
                                    <option value="">Not selected</option>
                                    @foreach ($demoVehicles as $vehicle)
                                        <option value="{{ $vehicle->id }}">{{ $vehicle->plate_number }} - {{ $vehicle->model }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="form-field">
                                <span>Incident type</span>
                                <select name="type" required>
                                    @foreach ($demoTypes as $type)
                                        <option value="{{ $type }}">{{ str($type)->replace('_', ' ')->title() }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        <div class="form-actions">
                            <button class="app-button app-button-primary" type="submit">Create demo analysis</button>
                        </div>
                    </form>
                @endif
            </section>
        @endif

        <nav class="admin-filters" aria-label="AI analysis status filters">
            @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'all' => 'All'] as $value => $label)
                <a class="admin-filter-link @if ($aiStatus === $value) is-active @endif" href="{{ route('incident-reviews.index', ['tab' => 'ai', 'status' => $value]) }}">{{ $label }}</a>
            @endforeach
        </nav>

        <section class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Incident</th>
                        <th>Type</th>
                        <th>Severity</th>
                        <th>Description</th>
                        <th>Driver</th>
                        <th>Vehicle</th>
                        <th>AI status</th>
                        <th>Review status</th>
                        <th>Confidence</th>
                        <th>Summary</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($aiAnalyses as $analysis)
                        @php
                            $summaryFallback = match ($analysis->status) {
                                \App\Models\AIAnalysis::STATUS_PENDING => 'Queued for AI processing.',
                                \App\Models\AIAnalysis::STATUS_PROCESSING => 'Processing uploaded media and selecting important frames.',
                                \App\Models\AIAnalysis::STATUS_AI_ANALYZING => 'AI is analyzing selected dashcam frames.',
                                \App\Models\AIAnalysis::STATUS_FAILED => 'AI analysis failed. Manual review recommended.',
                                default => 'No AI observation summary is available.',
                            };
                            $summaryText = $analysis->summary ?: $summaryFallback;
                        @endphp
                        <tr>
                            <td>#{{ $analysis->incident->id }}</td>
                            <td>{{ str($analysis->incident->type)->replace('_', ' ')->title() }}</td>
                            <td><x-status-badge :status="$analysis->incident->severity" /></td>
                            <td><x-compact-text :text="$analysis->incident->description" /></td>
                            <td>{{ $analysis->incident->driver->user->name }}</td>
                            <td>{{ $analysis->incident->vehicle?->plate_number ?? 'Not selected' }}</td>
                            <td><x-status-badge :status="$analysis->status" /></td>
                            <td><x-status-badge :status="$analysis->incident->activeReview ? 'reviewed' : $analysis->incident->status" /></td>
                            <td>{{ $analysis->confidence_score !== null ? number_format($analysis->confidence_score, 2) : 'Pending' }}</td>
                            <td><x-compact-text :text="$summaryText" /></td>
                            <td>
                                <div class="inline-actions">
                                    <x-action-icon name="view" label="View incident" :href="route('incidents.show', $analysis->incident)" />
                                    @if ($analysis->is_active)
                                        @can('deactivate', $analysis)
                                            <form method="POST" action="{{ route('ai-analyses.deactivate', $analysis) }}" data-confirm="Deactivate this AI analysis?">
                                                @csrf
                                                @method('PATCH')
                                                <x-action-icon name="deactivate" label="Deactivate AI analysis" type="submit" />
                                            </form>
                                        @endcan
                                    @else
                                        @can('reactivate', $analysis)
                                            <form method="POST" action="{{ route('ai-analyses.reactivate', $analysis) }}">
                                                @csrf
                                                @method('PATCH')
                                                <x-action-icon name="reactivate" label="Reactivate AI analysis" type="submit" />
                                            </form>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11">No AI analyses found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <div class="admin-pagination">{{ $aiAnalyses->links() }}</div>
    @endif
@endsection
