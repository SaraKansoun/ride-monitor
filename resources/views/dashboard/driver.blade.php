@extends('layouts.app')

@section('title', 'Driver workspace')

@section('content')
    @php
        $metricValues = collect($metrics)->mapWithKeys(fn ($metric) => [strtolower($metric['label']) => $metric['value']]);
        $activeIncidents = (int) $metricValues->get('my active incidents', 0);
        $resolvedIncidents = (int) $metricValues->get('my resolved incidents', 0);
        $pendingIncidents = (int) $metricValues->get('my pending incidents', 0);
        $scoreRaw = $metricValues->get('my current safety score', 'N/A');
        $scoreValue = is_numeric($scoreRaw) ? (int) $scoreRaw : 0;
        $incidentMax = max($activeIncidents, $resolvedIncidents, $pendingIncidents, 1);
        $driverImage = 'https://images.unsplash.com/photo-1449965408869-eaa3f722e40d?auto=format&fit=crop&w=1400&q=80';
        $scoreBand = $scoreValue >= 80 ? 'Strong performance' : ($scoreValue >= 50 ? 'Needs attention' : 'High risk');
        $visibleNotificationItems = collect($notificationItems)
            ->reject(fn (array $item): bool => ($item['status'] ?? null) === 'completed')
            ->values();
        $driverActions = [
            [
                'label' => 'Report incident',
                'description' => 'Submit a new safety report with type, description, vehicle, and media.',
                'icon' => 'incidents',
                'href' => route('incidents.create'),
            ],
            [
                'label' => 'View my incidents',
                'description' => 'Review your submitted reports, statuses, media, and final decisions.',
                'icon' => 'performance',
                'href' => route('incidents.index'),
            ],
        ];
    @endphp

    <section class="dashboard-hero dashboard-hero-driver">
        <div class="dashboard-hero-content">
            <p class="app-kicker">Driver dashboard</p>
            <h2>Driver workspace</h2>
            @if ($driver)
                <p>Your personal safety overview shows your incidents, review progress, and current driver performance without exposing other drivers' data.</p>
                <div class="dashboard-hero-meta">
                    <span>{{ $driver->user?->name ?? 'Driver' }}</span>
                    <x-status-badge :status="$driver->status" />
                </div>
                <div class="dashboard-hero-stats">
                    <span>
                        <strong>{{ $scoreRaw }}</strong>
                        Safety score
                    </span>
                    <span>
                        <strong>{{ $activeIncidents }}</strong>
                        Active incidents
                    </span>
                    <span>
                        <strong>{{ $scoreBand }}</strong>
                        Current band
                    </span>
                </div>
            @else
                <p>No driver profile is linked to your account yet. Dashboard counts will appear after an admin creates your driver profile.</p>
            @endif
        </div>
        <div class="taxi-hero-visual" aria-hidden="true">
            <img src="{{ asset('images/project-icon.svg') }}" alt="">
            <span>On route</span>
        </div>
    </section>

    <section class="dashboard-grid driver-metric-grid">
        @foreach ($metrics as $metric)
            @php
                $cardIcon = match ($metric['label']) {
                    'my active incidents', 'My active incidents' => 'incidents',
                    'my resolved incidents', 'My resolved incidents' => 'completed',
                    'my pending incidents', 'My pending incidents' => 'pending',
                    'my current safety score', 'My current safety score' => 'safety',
                    default => 'drivers',
                };
            @endphp
            <article class="summary-card dashboard-metric-card">
                <x-nav-icon :name="$cardIcon" class="summary-icon" />
                <span class="summary-label">{{ $metric['label'] }}</span>
                <strong>{{ $metric['value'] }}</strong>
                <span class="summary-trend">Personal safety metric</span>
            </article>
        @endforeach
    </section>

    <section class="dashboard-analytics-grid dashboard-analytics-driver driver-card-row">
        <article class="chart-card score-card">
            <div>
                <p class="app-kicker">Safety score</p>
                <h2 class="section-title">Current performance</h2>
            </div>

            <div class="score-gauge-wrap">
                <meter class="score-gauge" min="0" max="100" low="50" high="80" optimum="100" value="{{ $scoreValue }}">{{ $scoreRaw }}</meter>
                <div class="score-gauge-value">
                    <strong>{{ $scoreRaw }}</strong>
                    <span>out of 100</span>
                </div>
            </div>
        </article>

        <x-line-chart
            class="xl:col-span-2"
            title="Driver Safety Score Trend"
            kicker="Score analytics"
            copy="Your score movement after active final human reviews."
            :points="$scoreTrendPoints"
            empty="No final review history is available yet."
        />

        <article class="chart-card driver-incident-stat-card">
            <div>
                <p class="app-kicker">Incident statistics</p>
                <h2 class="section-title">My incident mix</h2>
            </div>

            <div class="chart-list">
                <div class="chart-row">
                    <span>Active</span>
                    <meter class="dashboard-meter" min="0" max="{{ $incidentMax }}" value="{{ $activeIncidents }}">{{ $activeIncidents }}</meter>
                    <strong>{{ $activeIncidents }}</strong>
                </div>
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
            </div>
        </article>

        <article class="dashboard-photo-card driver-overview-photo-card xl:col-span-2">
            <img src="{{ $driverImage }}" alt="Driver view from inside a moving car">
            <div>
                <p class="app-kicker">Trip safety</p>
                <h2 class="section-title">Focused driver overview</h2>
                <p class="section-copy">Your dashboard keeps reports, review status, and safety score easy to scan.</p>
            </div>
        </article>
    </section>

    <section class="workspace-panel driver-progress-panel">
        <div>
            <p class="app-kicker">Incident progress</p>
            <h2 class="section-title">Personal safety timeline</h2>
        </div>

        <div class="performance-stat-grid">
            <div class="performance-stat">
                <span>Active reports</span>
                <strong>{{ $activeIncidents }}</strong>
                <small>Visible active incident records</small>
            </div>
            <div class="performance-stat">
                <span>Pending reports</span>
                <strong>{{ $pendingIncidents }}</strong>
                <small>Waiting for monitor review</small>
            </div>
            <div class="performance-stat">
                <span>Resolved reports</span>
                <strong>{{ $resolvedIncidents }}</strong>
                <small>Completed human review decisions</small>
            </div>
        </div>
    </section>

    <section class="dashboard-split-grid driver-card-row">
        <article @class([
            'workspace-panel dashboard-panel-fill',
            'xl:col-span-2' => $visibleNotificationItems->isEmpty(),
        ])>
            <div>
                <p class="app-kicker">Safety score guide</p>
                <h2 class="section-title">How to read your score</h2>
            </div>

            <p class="section-copy">Your score starts at 100 and changes only after a human monitor resolves an incident review.</p>

            <div class="score-guide">
                <div>
                    <span>80-100</span>
                    <strong>Strong performance</strong>
                </div>
                <div>
                    <span>50-79</span>
                    <strong>Needs attention</strong>
                </div>
                <div>
                    <span>0-49</span>
                    <strong>High risk</strong>
                </div>
            </div>
        </article>

        @if ($visibleNotificationItems->isNotEmpty())
            <article class="workspace-panel dashboard-panel-fill">
                <div>
                    <p class="app-kicker">Notifications</p>
                    <h2 class="section-title">Driver attention panel</h2>
                </div>

                <div class="notification-list">
                    @foreach ($visibleNotificationItems as $item)
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
        @endif
    </section>

    @if ($recentIncidents->isNotEmpty())
        <section class="workspace-panel">
            <div class="admin-header">
                <div>
                    <p class="app-kicker">Recent activity</p>
                    <h2 class="section-title">My latest incidents</h2>
                </div>
                <a class="workspace-link" href="{{ route('incidents.index') }}">View all</a>
            </div>

            <div class="compact-list">
                @foreach ($recentIncidents as $incident)
                    <a class="compact-list-item" href="{{ route('incidents.show', $incident) }}">
                        <span>
                            <strong>{{ $incident->description }}</strong>
                            <small>{{ $incident->created_at?->format('Y-m-d H:i') }} - {{ $incident->vehicle?->plate_number ?? 'No vehicle' }}</small>
                        </span>
                        <x-status-badge :status="$incident->status" />
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="workspace-panel driver-quick-actions-panel">
        <div>
            <p class="app-kicker">Quick actions</p>
            <h2 class="section-title">Driver workspace</h2>
        </div>

        @if ($driver)
            <p class="section-copy">Your dashboard shows only your incidents and current safety score.</p>

            <div class="quick-action-grid driver-action-grid">
                @foreach ($driverActions as $action)
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

            <dl class="detail-grid">
                <div>
                    <dt>Driver</dt>
                    <dd>{{ $driver->user?->name ?? 'Driver' }}</dd>
                </div>

                <div>
                    <dt>Driver status</dt>
                    <dd><x-status-badge :status="$driver->status" /></dd>
                </div>

                @if ($latestIncident)
                    <div>
                        <dt>Latest incident status</dt>
                        <dd><x-status-badge :status="$latestIncident->status" /></dd>
                    </div>

                    <div>
                        <dt>Latest incident</dt>
                        <dd>
                            <a href="{{ route('incidents.show', $latestIncident) }}">{{ $latestIncident->description }}</a>
                        </dd>
                    </div>
                @endif
            </dl>
        @else
            <p class="section-copy">No driver profile is linked to your account yet. Dashboard counts will appear after an admin creates your driver profile.</p>
        @endif
    </section>
@endsection
