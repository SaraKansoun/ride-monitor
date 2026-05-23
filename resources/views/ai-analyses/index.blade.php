@extends('layouts.app')

@section('title', 'AI Analyses')

@section('content')
    <section class="admin-header">
        <div>
            <p class="app-kicker">Advisory only</p>
            <h2 class="section-title">AI Analyses</h2>
            <p class="section-copy">Dashcam media is screened locally first, then selected important frames are escalated to OpenAI only when needed.</p>
        </div>
    </section>

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
            <a class="admin-filter-link @if ($status === $value) is-active @endif" href="{{ route('ai-analyses.index', ['status' => $value]) }}">{{ $label }}</a>
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
                @forelse ($analyses as $analysis)
                    @php
                        $summaryFallback = match ($analysis->status) {
                            \App\Models\AIAnalysis::STATUS_PENDING => 'Queued for AI processing.',
                            \App\Models\AIAnalysis::STATUS_PROCESSING => 'Processing uploaded media and selecting important frames.',
                            \App\Models\AIAnalysis::STATUS_AI_ANALYZING => 'AI is analyzing selected dashcam frames.',
                            \App\Models\AIAnalysis::STATUS_FAILED => 'AI analysis failed. Manual review recommended.',
                            default => 'No AI observation summary is available.',
                        };
                    @endphp
                    <tr>
                        <td>#{{ $analysis->incident->id }}</td>
                        <td>{{ str($analysis->incident->type)->replace('_', ' ')->title() }}</td>
                        <td><x-status-badge :status="$analysis->incident->severity" /></td>
                        <td>{{ str($analysis->incident->description)->limit(80) }}</td>
                        <td>{{ $analysis->incident->driver->user->name }}</td>
                        <td>{{ $analysis->incident->vehicle?->plate_number ?? 'Not selected' }}</td>
                        <td><x-status-badge :status="$analysis->status" /></td>
                        <td><x-status-badge :status="$analysis->incident->activeReview ? 'reviewed' : $analysis->incident->status" /></td>
                        <td>{{ $analysis->confidence_score !== null ? number_format($analysis->confidence_score, 2) : 'Pending' }}</td>
                        <td>{{ $analysis->summary ? str($analysis->summary)->limit(80) : $summaryFallback }}</td>
                        <td>
                            <div class="inline-actions">
                                <a href="{{ route('incidents.show', $analysis->incident) }}">View incident</a>
                                @if ($analysis->is_active)
                                    @can('deactivate', $analysis)
                                        <form method="POST" action="{{ route('ai-analyses.deactivate', $analysis) }}" data-confirm="Deactivate this AI analysis?">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit">Deactivate</button>
                                        </form>
                                    @endcan
                                @else
                                    @can('reactivate', $analysis)
                                        <form method="POST" action="{{ route('ai-analyses.reactivate', $analysis) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit">Reactivate</button>
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

    <div class="admin-pagination">{{ $analyses->links() }}</div>
@endsection
