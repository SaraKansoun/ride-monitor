@extends('layouts.app')

@section('title', 'Monitor workspace')

@section('content')
    @php
        $metricValues = collect($metrics)->mapWithKeys(fn ($metric) => [strtolower($metric['label']) => (int) $metric['value']]);
        $pendingIncidents = $metricValues->get('pending incidents', 0);
        $underReviewIncidents = $metricValues->get('incidents under review', 0);
        $resolvedIncidents = $metricValues->get('resolved incidents', 0);
        $reviewTotal = max($pendingIncidents + $underReviewIncidents + $resolvedIncidents, 1);
        $completionRate = (int) round(($resolvedIncidents / $reviewTotal) * 100);
        $monitorImage = 'https://images.unsplash.com/photo-1493238792000-8113da705763?auto=format&fit=crop&w=1400&q=80';
    @endphp

    <section class="dashboard-hero dashboard-hero-monitor">
        <div class="dashboard-hero-content">
            <p class="app-kicker">Monitor dashboard</p>
            <h2>Incident review center</h2>
            <p>Prioritize pending reports, review risk signals, and keep every final safety decision grounded in human judgment.</p>

            <div class="dashboard-hero-actions">
                <a class="app-button app-button-primary" href="{{ route('incident-reviews.index') }}">Open reviews</a>
                <a class="app-button app-button-muted" href="{{ route('incidents.index') }}">View incidents</a>
            </div>

            <div class="dashboard-hero-stats">
                <span>
                    <strong>{{ $pendingIncidents }}</strong>
                    Pending
                </span>
                <span>
                    <strong>{{ $underReviewIncidents }}</strong>
                    Under review
                </span>
                <span>
                    <strong>{{ $completionRate }}%</strong>
                    Completion rate
                </span>
            </div>
        </div>
        <div class="taxi-hero-visual" aria-hidden="true">
            <img src="{{ asset('images/project-icon.svg') }}" alt="">
            <span>Review desk</span>
        </div>
    </section>

    <section class="dashboard-grid">
        @foreach ($metrics as $metric)
            @php
                $cardIcon = match ($metric['label']) {
                    'Pending incidents' => 'pending',
                    'Incidents under review' => 'reviews',
                    'Resolved incidents' => 'completed',
                    default => 'incidents',
                };
            @endphp
            <article class="summary-card dashboard-metric-card">
                <x-nav-icon :name="$cardIcon" class="summary-icon" />
                <span class="summary-label">{{ $metric['label'] }}</span>
                <strong>{{ $metric['value'] }}</strong>
                <span class="summary-trend">Monitor workload signal</span>
            </article>
        @endforeach
    </section>

    <section class="dashboard-analytics-grid">
        <x-line-chart
            class="xl:col-span-3"
            title="Incidents Over Time"
            kicker="Trend analytics"
            copy="Active incident reports created during the last 14 days."
            :points="$incidentTrendPoints"
            empty="No active incidents were reported in the last 14 days."
        />
    </section>

    <section class="dashboard-analytics-grid">
        <article class="chart-card">
            <div>
                <p class="app-kicker">Review workload</p>
                <h2 class="section-title">Pending reviews queue</h2>
            </div>

            <div class="compact-list">
                @forelse ($pendingReviewIncidents as $incident)
                    <a class="compact-list-item" href="{{ route('incidents.show', $incident) }}">
                        <span>
                            <strong>{{ $incident->description }}</strong>
                            <small>{{ $incident->driver?->user?->name ?? 'Unassigned driver' }} - {{ $incident->vehicle?->plate_number ?? 'No vehicle' }}</small>
                        </span>
                        <x-status-badge :status="$incident->status" />
                    </a>
                @empty
                    <div class="empty-state">
                        <strong>No pending reviews.</strong>
                        <span>All active incidents currently have a final review or no open workload.</span>
                    </div>
                @endforelse
            </div>
        </article>

        <article class="chart-card chart-card-feature">
            <div>
                <p class="app-kicker">Review analytics</p>
                <h2 class="section-title">Resolution completion rate</h2>
            </div>

            <div class="resolution-card resolution-card-light">
                <h3>{{ $completionRate }}%</h3>
                <span>Resolved incidents compared with current review workload.</span>
                <meter class="score-gauge" min="0" max="100" value="{{ $completionRate }}">{{ $completionRate }}%</meter>
            </div>

            <div class="chart-list">
                <div class="chart-row">
                    <span>Pending</span>
                    <meter class="dashboard-meter meter-pending" min="0" max="{{ $reviewTotal }}" value="{{ $pendingIncidents }}">{{ $pendingIncidents }}</meter>
                    <strong>{{ $pendingIncidents }}</strong>
                </div>
                <div class="chart-row">
                    <span>Reviewing</span>
                    <meter class="dashboard-meter meter-busy" min="0" max="{{ $reviewTotal }}" value="{{ $underReviewIncidents }}">{{ $underReviewIncidents }}</meter>
                    <strong>{{ $underReviewIncidents }}</strong>
                </div>
                <div class="chart-row">
                    <span>Resolved</span>
                    <meter class="dashboard-meter meter-completed" min="0" max="{{ $reviewTotal }}" value="{{ $resolvedIncidents }}">{{ $resolvedIncidents }}</meter>
                    <strong>{{ $resolvedIncidents }}</strong>
                </div>
            </div>
        </article>

        <article class="chart-card">
            <div>
                <p class="app-kicker">Safety attention</p>
                <h2 class="section-title">Lowest score watchlist</h2>
            </div>

            <div class="driver-card-list">
                @forelse ($riskyDrivers as $driver)
                    <div class="driver-mini-card">
                        <div>
                            <strong>{{ $driver->user?->name ?? 'Unassigned user' }}</strong>
                            <span>{{ $driver->score?->total_incidents ?? 0 }} reviewed incidents - {{ $driver->score?->unsafe_events ?? 0 }} unsafe events</span>
                        </div>
                        <b>{{ $driver->score?->score ?? 100 }}</b>
                    </div>
                @empty
                    <div class="empty-state">
                        <strong>No active scores yet.</strong>
                        <span>Driver safety scores will appear after active driver profiles are available.</span>
                    </div>
                @endforelse
            </div>
        </article>

        <article class="dashboard-photo-card">
            <img src="{{ $monitorImage }}" alt="Car dashboard and city driving view">
            <div>
                <p class="app-kicker">Human decision</p>
                <h2 class="section-title">Reviews stay monitor-led</h2>
                <p class="section-copy">AI observations can support the review, but the final safety decision stays with the monitor.</p>
            </div>
        </article>
    </section>

    <section class="workspace-panel">
        <div>
            <p class="app-kicker">Notifications</p>
            <h2 class="section-title">Monitor attention panel</h2>
        </div>

        <div class="notification-list">
            @foreach ($notificationItems as $item)
                <div class="notification-item">
                    <span class="notification-dot"></span>
                    <div>
                        <strong>{{ $item['title'] }}</strong>
                        <small>{{ $item['copy'] }}</small>
                    </div>
                    <x-status-badge :status="$item['status']" />
                </div>
            @endforeach
        </div>
    </section>

    <section class="workspace-panel">
        <div class="admin-header">
            <div>
                <p class="app-kicker">Monitor dashboard</p>
                <h2 class="section-title">Recent incidents</h2>
            </div>
        </div>

        <div class="admin-filters">
            @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'all' => 'All'] as $filter => $label)
                <a class="admin-filter-link @if ($status === $filter) is-active @endif" href="{{ route('dashboard.monitor', ['status' => $filter]) }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Incident</th>
                        <th>Driver</th>
                        <th>Vehicle</th>
                        <th>Status</th>
                        <th>Reported</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentIncidents as $incident)
                        <tr>
                            <td><a href="{{ route('incidents.show', $incident) }}">{{ $incident->description }}</a></td>
                            <td>{{ $incident->driver?->user?->name ?? 'Unassigned' }}</td>
                            <td>{{ $incident->vehicle?->plate_number ?? 'Unassigned' }}</td>
                            <td><x-status-badge :status="$incident->status" /></td>
                            <td>{{ $incident->created_at?->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">No incidents found for this filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="workspace-panel">
        <div>
            <p class="app-kicker">Safety attention</p>
            <h2 class="section-title">Risky active drivers by lowest score</h2>
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Driver</th>
                        <th>Status</th>
                        <th>Current score</th>
                        <th>Total incidents</th>
                        <th>Unsafe events</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($riskyDrivers as $driver)
                        <tr>
                            <td>{{ $driver->user?->name ?? 'Unassigned user' }}</td>
                            <td><x-status-badge :status="$driver->status" /></td>
                            <td>{{ $driver->score?->score ?? 100 }}</td>
                            <td>{{ $driver->score?->total_incidents ?? 0 }}</td>
                            <td>{{ $driver->score?->unsafe_events ?? 0 }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">No active driver scores are available yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
