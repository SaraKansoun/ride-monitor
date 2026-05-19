@extends('layouts.app')

@section('title', 'Admin workspace')

@section('content')
    @php
        $metricValues = collect($metrics)->mapWithKeys(fn ($metric) => [strtolower($metric['label']) => (int) $metric['value']]);
        $activeUsers = $metricValues->get('total active users', 0);
        $inactiveUsers = $metricValues->get('total inactive users', 0);
        $activeDrivers = $metricValues->get('total active drivers', 0);
        $activeVehicles = $metricValues->get('total active vehicles', 0);
        $activeIncidents = $metricValues->get('total active incidents', 0);
        $pendingIncidents = $metricValues->get('pending incidents', 0);
        $resolvedIncidents = $metricValues->get('resolved incidents', 0);
        $underReviewIncidents = max($activeIncidents - $pendingIncidents - $resolvedIncidents, 0);
        $openReviewLoad = $pendingIncidents + $underReviewIncidents;
        $resolutionRate = $activeIncidents > 0 ? (int) round(($resolvedIncidents / $activeIncidents) * 100) : 0;
        $incidentMax = max($activeIncidents, $pendingIncidents, $resolvedIncidents, 1);
        $fleetMax = max($activeUsers, $inactiveUsers, $activeDrivers, $activeVehicles, 1);
        $fleetImage = 'https://images.unsplash.com/photo-1758950497906-630b76200a3b?auto=format&fit=crop&w=1400&q=80';
        $quickActions = [
            [
                'label' => 'Manage users',
                'description' => 'Create accounts, update roles, and keep staff access organized.',
                'icon' => 'users',
                'href' => route('admin.users.index'),
            ],
            [
                'label' => 'Manage drivers',
                'description' => 'Review driver profiles, license details, and activation status.',
                'icon' => 'drivers',
                'href' => route('admin.drivers.index'),
            ],
            [
                'label' => 'Manage vehicles',
                'description' => 'Track fleet records, plate numbers, availability, and status.',
                'icon' => 'vehicles',
                'href' => route('admin.vehicles.index'),
            ],
            [
                'label' => 'Assign vehicles',
                'description' => 'Connect active drivers with available vehicles for current work.',
                'icon' => 'assignments',
                'href' => route('admin.assignments.index'),
            ],
            [
                'label' => 'View incidents',
                'description' => 'Open safety reports, media, review state, and incident history.',
                'icon' => 'incidents',
                'href' => route('incidents.index'),
            ],
        ];
    @endphp

    <section class="dashboard-hero dashboard-hero-admin">
        <div class="dashboard-hero-content">
            <p class="app-kicker">Admin dashboard</p>
            <h2>Admin workspace</h2>
            <p>Track users, drivers, vehicles, and incident activity from a clean command center built for quick presentation and daily operations.</p>

            <div class="dashboard-hero-actions">
                <a class="app-button app-button-primary" href="{{ route('admin.users.index') }}">Manage users</a>
                <a class="app-button app-button-muted" href="{{ route('incidents.index') }}">View incidents</a>
            </div>

            <div class="dashboard-hero-stats">
                <span>
                    <strong>{{ $activeDrivers }}</strong>
                    Active drivers
                </span>
                <span>
                    <strong>{{ $activeVehicles }}</strong>
                    Active vehicles
                </span>
                <span>
                    <strong>{{ $resolutionRate }}%</strong>
                    Resolution rate
                </span>
            </div>
        </div>
        <div class="taxi-hero-visual" aria-hidden="true">
            <img src="{{ asset('images/project-icon.svg') }}" alt="">
            <span>Fleet control</span>
        </div>
    </section>

    <section class="dashboard-grid">
        @foreach ($metrics as $metric)
            @php
                $cardIcon = match ($metric['label']) {
                    'total active users', 'Total active users', 'total inactive users', 'Total inactive users' => 'users',
                    'total active drivers', 'Total active drivers' => 'drivers',
                    'total active vehicles', 'Total active vehicles' => 'vehicles',
                    'total active incidents', 'Total active incidents' => 'incidents',
                    'pending incidents', 'Pending incidents', 'pending active incidents', 'Pending active incidents' => 'pending',
                    'resolved incidents', 'Resolved incidents', 'resolved active incidents', 'Resolved active incidents' => 'completed',
                    default => 'dashboard',
                };
            @endphp
            <article class="summary-card dashboard-metric-card">
                <x-nav-icon :name="$cardIcon" class="summary-icon" />
                <span class="summary-label">{{ $metric['label'] }}</span>
                <strong>{{ $metric['value'] }}</strong>
                <span class="summary-trend">Live operational count</span>
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
        <article class="chart-card chart-card-feature">
            <div>
                <p class="app-kicker">Incident statistics</p>
                <h2 class="section-title">Active incident snapshot</h2>
            </div>

            <div class="chart-list">
                <div class="chart-row">
                    <span>Pending</span>
                    <meter class="dashboard-meter meter-pending" min="0" max="{{ $incidentMax }}" value="{{ $pendingIncidents }}">{{ $pendingIncidents }}</meter>
                    <strong>{{ $pendingIncidents }}</strong>
                </div>
                <div class="chart-row">
                    <span>Resolved</span>
                    <meter class="dashboard-meter meter-completed" min="0" max="{{ $incidentMax }}" value="{{ $resolvedIncidents }}">{{ $resolvedIncidents }}</meter>
                    <strong>{{ $resolvedIncidents }}</strong>
                </div>
                <div class="chart-row">
                    <span>In review</span>
                    <meter class="dashboard-meter meter-busy" min="0" max="{{ $incidentMax }}" value="{{ $underReviewIncidents }}">{{ $underReviewIncidents }}</meter>
                    <strong>{{ $underReviewIncidents }}</strong>
                </div>
                <div class="chart-row">
                    <span>Total active</span>
                    <meter class="dashboard-meter" min="0" max="{{ $incidentMax }}" value="{{ $activeIncidents }}">{{ $activeIncidents }}</meter>
                    <strong>{{ $activeIncidents }}</strong>
                </div>
            </div>
        </article>

        <article class="chart-card chart-card-feature">
            <div>
                <p class="app-kicker">Fleet statistics</p>
                <h2 class="section-title">Operations mix</h2>
            </div>

            <div class="chart-list">
                <div class="chart-row">
                    <span>Active users</span>
                    <meter class="dashboard-meter" min="0" max="{{ $fleetMax }}" value="{{ $activeUsers }}">{{ $activeUsers }}</meter>
                    <strong>{{ $activeUsers }}</strong>
                </div>
                <div class="chart-row">
                    <span>Drivers</span>
                    <meter class="dashboard-meter meter-completed" min="0" max="{{ $fleetMax }}" value="{{ $activeDrivers }}">{{ $activeDrivers }}</meter>
                    <strong>{{ $activeDrivers }}</strong>
                </div>
                <div class="chart-row">
                    <span>Vehicles</span>
                    <meter class="dashboard-meter meter-pending" min="0" max="{{ $fleetMax }}" value="{{ $activeVehicles }}">{{ $activeVehicles }}</meter>
                    <strong>{{ $activeVehicles }}</strong>
                </div>
            </div>
        </article>

        <article class="dashboard-photo-card">
            <img src="{{ $fleetImage }}" alt="Yellow taxi moving through a city street">
            <div>
                <p class="app-kicker">Presentation view</p>
                <h2 class="section-title">Taxi fleet command center</h2>
                <p class="section-copy">A clean visual overview for users, vehicles, incidents, and safety decisions.</p>
            </div>
        </article>
    </section>

    <section class="dashboard-command-grid">
        <article class="workspace-panel dashboard-panel-fill">
            <div>
                <p class="app-kicker">Fleet health</p>
                <h2 class="section-title">Fleet performance</h2>
            </div>

            <div class="fleet-performance-grid">
                @foreach ($fleetHealth as $item)
                    <div class="fleet-performance-card">
                        <div>
                            <span>{{ $item['label'] }}</span>
                            <strong>{{ $item['value'] }}</strong>
                        </div>
                        <x-status-badge :status="$item['status']" />
                    </div>
                @endforeach
            </div>
        </article>

        <article class="workspace-panel dashboard-panel-fill">
            <div>
                <p class="app-kicker">Notifications</p>
                <h2 class="section-title">Operational signals</h2>
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
        </article>
    </section>

    <section class="workspace-panel">
        <div class="admin-header">
            <div>
                <p class="app-kicker">Live monitoring</p>
                <h2 class="section-title">Latest active incidents</h2>
            </div>
            <a class="workspace-link" href="{{ route('incidents.index') }}">Open incidents</a>
        </div>

        <div class="live-monitor-grid">
            <div class="activity-feed">
                @forelse ($recentIncidents as $incident)
                    <a class="activity-feed-item" href="{{ route('incidents.show', $incident) }}">
                        <span class="activity-marker"></span>
                        <span>
                            <strong>{{ $incident->description }}</strong>
                            <small>{{ $incident->driver?->user?->name ?? 'Unassigned driver' }} - {{ $incident->vehicle?->plate_number ?? 'No vehicle' }}</small>
                        </span>
                        <x-status-badge :status="$incident->status" />
                    </a>
                @empty
                    <div class="empty-state">
                        <strong>No active incidents yet.</strong>
                        <span>New reports will appear here as soon as drivers submit them.</span>
                    </div>
                @endforelse
            </div>

            <div class="resolution-card">
                <p class="app-kicker">Resolution analytics</p>
                <h3>{{ $resolutionRate }}%</h3>
                <span>Current active incident resolution rate</span>
                <meter class="score-gauge" min="0" max="100" value="{{ $resolutionRate }}">{{ $resolutionRate }}%</meter>
            </div>
        </div>
    </section>

    <section class="dashboard-split-grid">
        <article class="workspace-panel dashboard-panel-fill">
            <div>
                <p class="app-kicker">Performance analytics</p>
                <h2 class="section-title">Fleet safety snapshot</h2>
            </div>

            <div class="performance-stat-grid">
                <div class="performance-stat">
                    <span>Open workload</span>
                    <strong>{{ $openReviewLoad }}</strong>
                    <small>Pending and under-review incidents</small>
                </div>
                <div class="performance-stat">
                    <span>Resolution rate</span>
                    <strong>{{ $resolutionRate }}%</strong>
                    <small>Resolved from active incident total</small>
                </div>
                <div class="performance-stat">
                    <span>Fleet coverage</span>
                    <strong>{{ $activeVehicles }}</strong>
                    <small>Active vehicles in service records</small>
                </div>
            </div>
        </article>

        <article class="workspace-panel dashboard-panel-fill">
            <div>
                <p class="app-kicker">Driver availability</p>
                <h2 class="section-title">Active operations overview</h2>
            </div>

            <div class="availability-map">
                <div>
                    <span>Drivers</span>
                    <strong>{{ $activeDrivers }}</strong>
                </div>
                <div>
                    <span>Vehicles</span>
                    <strong>{{ $activeVehicles }}</strong>
                </div>
                <div>
                    <span>Open reviews</span>
                    <strong>{{ $openReviewLoad }}</strong>
                </div>
                <div>
                    <span>Resolved</span>
                    <strong>{{ $resolvedIncidents }}</strong>
                </div>
            </div>
        </article>
    </section>

    <section class="workspace-panel quick-actions-panel">
        <div>
            <p class="app-kicker">Quick actions</p>
            <h2 class="section-title">Manage the system</h2>
        </div>

        <p class="section-copy">Jump into the core modules used most often during a fleet safety presentation or daily operations review.</p>

        <div class="quick-action-grid">
            @foreach ($quickActions as $action)
                <a class="quick-action-card group" href="{{ $action['href'] }}">
                    <x-nav-icon :name="$action['icon']" class="quick-action-icon" />

                    <span class="quick-action-content">
                        <strong>{{ $action['label'] }}</strong>
                        <small>{{ $action['description'] }}</small>
                    </span>

                    <span class="quick-action-meta">Open</span>
                </a>
            @endforeach
        </div>
    </section>
@endsection
