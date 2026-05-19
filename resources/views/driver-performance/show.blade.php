@extends('layouts.app')

@section('title', 'Driver Performance')

@section('content')
    @php
        $totalActiveIncidents = array_sum($incidentStatusCounts);
        $incidentMax = max($totalActiveIncidents, max($incidentStatusCounts), max($incidentTypeCounts), max($severityCounts), 1);
        $pointsRetained = max(0, $scoreMax - $pointsLost);
        $scorePercent = $scoreMax > 0 ? round(($scoreValue / $scoreMax) * 100) : 0;
        $quickActions = [
            [
                'description' => 'Submit a new safety report with type, vehicle, description, and media.',
                'href' => route('incidents.create'),
                'icon' => 'incidents',
                'label' => 'Report incident',
            ],
            [
                'description' => 'Open your incident list with statuses, media, reviews, and AI observations.',
                'href' => route('incidents.index'),
                'icon' => 'performance',
                'label' => 'View my incidents',
            ],
        ];
    @endphp

    <section class="dashboard-hero dashboard-hero-driver performance-hero">
        <div class="dashboard-hero-content">
            <p class="app-kicker">Driver performance</p>
            <h2>Your safety performance report</h2>
            <p>This report is based on final human reviews for your active incidents. AI observations are advisory only and never decide your score.</p>

            <div class="dashboard-hero-meta">
                <span>{{ $driver->user?->name ?? 'Driver' }}</span>
                <x-status-badge :status="$driver->status" />
                <x-status-badge :status="$scoreBand['status']" />
            </div>

            <div class="dashboard-hero-stats">
                <span>
                    <strong>{{ $scoreValue }}</strong>
                    Current score
                </span>
                <span>
                    <strong>{{ $scoreBand['label'] }}</strong>
                    Score band
                </span>
                <span>
                    <strong>{{ $score->total_incidents }}</strong>
                    Final reviews
                </span>
            </div>
        </div>

        <div class="performance-hero-score" aria-label="Current score {{ $scoreValue }} out of {{ $scoreMax }}">
            <meter class="score-gauge" min="0" max="{{ $scoreMax }}" low="50" high="80" optimum="{{ $scoreMax }}" value="{{ $scoreValue }}">{{ $scoreValue }}</meter>
            <strong>{{ $scoreValue }}</strong>
            <span>{{ $scorePercent }}% retained</span>
        </div>
    </section>

    <section class="dashboard-grid">
        <article class="summary-card dashboard-metric-card">
            <x-nav-icon name="safety" class="summary-icon" />
            <span class="summary-label">Current score</span>
            <strong>{{ $scoreValue }}</strong>
            <span class="summary-trend">{{ $scoreBand['label'] }}</span>
        </article>

        <article class="summary-card dashboard-metric-card">
            <x-nav-icon name="completed" class="summary-icon" />
            <span class="summary-label">Reviewed incidents</span>
            <strong>{{ $score->total_incidents }}</strong>
            <span class="summary-trend">Human final reviews</span>
        </article>

        <article class="summary-card dashboard-metric-card">
            <x-nav-icon name="pending" class="summary-icon" />
            <span class="summary-label">Pending incidents</span>
            <strong>{{ $incidentStatusCounts['pending'] }}</strong>
            <span class="summary-trend">Waiting for review</span>
        </article>

        <article class="summary-card dashboard-metric-card">
            <x-nav-icon name="incidents" class="summary-icon" />
            <span class="summary-label">Unsafe events</span>
            <strong>{{ $score->unsafe_events }}</strong>
            <span class="summary-trend">From final reviews</span>
        </article>
    </section>

    <section class="dashboard-analytics-grid dashboard-analytics-driver">
        <article class="chart-card score-card performance-score-card">
            <div>
                <p class="app-kicker">Score impact</p>
                <h2 class="section-title">Points retained and lost</h2>
            </div>

            <div class="score-gauge-wrap">
                <meter class="score-gauge" min="0" max="{{ $scoreMax }}" low="50" high="80" optimum="{{ $scoreMax }}" value="{{ $scoreValue }}">{{ $scoreValue }}</meter>
                <div class="score-gauge-value">
                    <strong>{{ $scoreValue }}</strong>
                    <span>out of {{ $scoreMax }}</span>
                </div>
            </div>

            <div class="performance-stat-grid performance-stat-grid-tight">
                <div class="performance-stat performance-stat-dark">
                    <span>Points retained</span>
                    <strong>{{ $pointsRetained }}</strong>
                    <small>{{ $scoreBand['copy'] }}</small>
                </div>
                <div class="performance-stat performance-stat-dark">
                    <span>Points lost</span>
                    <strong>{{ $pointsLost }}</strong>
                    <small>Calculated from active final human reviews.</small>
                </div>
            </div>
        </article>

        <article class="chart-card">
            <div>
                <p class="app-kicker">Active incident statuses</p>
                <h2 class="section-title">Review progress</h2>
            </div>

            <div class="chart-list">
                <div class="chart-row">
                    <span>Pending</span>
                    <meter class="dashboard-meter meter-pending" min="0" max="{{ $incidentMax }}" value="{{ $incidentStatusCounts['pending'] }}">{{ $incidentStatusCounts['pending'] }}</meter>
                    <strong>{{ $incidentStatusCounts['pending'] }}</strong>
                </div>
                <div class="chart-row">
                    <span>Under review</span>
                    <meter class="dashboard-meter meter-busy" min="0" max="{{ $incidentMax }}" value="{{ $incidentStatusCounts['under_review'] }}">{{ $incidentStatusCounts['under_review'] }}</meter>
                    <strong>{{ $incidentStatusCounts['under_review'] }}</strong>
                </div>
                <div class="chart-row">
                    <span>Resolved</span>
                    <meter class="dashboard-meter meter-completed" min="0" max="{{ $incidentMax }}" value="{{ $incidentStatusCounts['resolved'] }}">{{ $incidentStatusCounts['resolved'] }}</meter>
                    <strong>{{ $incidentStatusCounts['resolved'] }}</strong>
                </div>
            </div>
        </article>

        <article class="chart-card">
            <div>
                <p class="app-kicker">Incident mix</p>
                <h2 class="section-title">Type and severity breakdown</h2>
            </div>

            <div class="performance-breakdown">
                @foreach ($incidentTypeCounts as $type => $count)
                    <div class="chart-row">
                        <span>{{ str_replace('_', ' ', $type) }}</span>
                        <meter class="dashboard-meter" min="0" max="{{ $incidentMax }}" value="{{ $count }}">{{ $count }}</meter>
                        <strong>{{ $count }}</strong>
                    </div>
                @endforeach
            </div>

            <div class="severity-chip-list">
                @foreach ($severityCounts as $severity => $count)
                    <span class="severity-chip">
                        <x-status-badge :status="$severity" />
                        <strong>{{ $count }}</strong>
                    </span>
                @endforeach
            </div>
        </article>
    </section>

    <section class="dashboard-split-grid">
        <article class="workspace-panel dashboard-panel-fill">
            <div>
                <p class="app-kicker">Driver profile</p>
                <h2 class="section-title">Performance details</h2>
            </div>

            <dl class="detail-grid">
                <div>
                    <dt>Driver</dt>
                    <dd>{{ $driver->user?->name ?? 'Driver' }}</dd>
                </div>

                <div>
                    <dt>Status</dt>
                    <dd><x-status-badge :status="$driver->status" /></dd>
                </div>

                <div>
                    <dt>License number</dt>
                    <dd>{{ $driver->license_number }}</dd>
                </div>

                <div>
                    <dt>Last updated</dt>
                    <dd>{{ $score->last_updated_at?->format('Y-m-d H:i') ?? 'Not updated' }}</dd>
                </div>

                <div>
                    <dt>Latest incident</dt>
                    <dd>
                        @if ($latestIncident)
                            <a href="{{ route('incidents.show', $latestIncident) }}">{{ $latestIncident->description }}</a>
                        @else
                            No active incidents yet.
                        @endif
                    </dd>
                </div>

                <div>
                    <dt>Latest status</dt>
                    <dd>
                        @if ($latestIncident)
                            <x-status-badge :status="$latestIncident->status" />
                        @else
                            No active incidents yet.
                        @endif
                    </dd>
                </div>
            </dl>
        </article>

        <article class="workspace-panel dashboard-panel-fill">
            <div>
                <p class="app-kicker">Assigned vehicles</p>
                <h2 class="section-title">Current assignments</h2>
            </div>

            <div class="compact-list">
                @forelse ($driver->currentAssignments as $assignment)
                    @if ($assignment->vehicle)
                        <div class="compact-list-item">
                            <span>
                                <strong>{{ $assignment->vehicle->plate_number }}</strong>
                                <small>{{ $assignment->vehicle->model }}{{ $assignment->vehicle->year ? ' - '.$assignment->vehicle->year : '' }}</small>
                            </span>
                            <x-status-badge :status="$assignment->vehicle->status" />
                        </div>
                    @endif
                @empty
                    <div class="empty-state">
                        <strong>No current vehicle assignment.</strong>
                        <span>Your assigned vehicles will appear here after fleet admins assign them.</span>
                    </div>
                @endforelse
            </div>
        </article>
    </section>

    <section class="workspace-panel">
        <div class="admin-header">
            <div>
                <p class="app-kicker">Review history</p>
                <h2 class="section-title">Recent final reviews</h2>
            </div>
            <a class="workspace-link" href="{{ route('incidents.index', ['status' => 'active', 'incident_status' => 'resolved']) }}">View resolved incidents</a>
        </div>

        <div class="performance-review-list">
            @forelse ($latestReviews as $review)
                @php
                    $incident = $review->incident;
                @endphp

                @if ($incident)
                    <a class="performance-review-card" href="{{ route('incidents.show', $incident) }}">
                        <span class="activity-marker"></span>
                        <span class="performance-review-content">
                            <strong>{{ $incident->description }}</strong>
                            <small>{{ $review->reviewed_at?->format('Y-m-d H:i') ?? 'Not reviewed' }} - {{ $incident->vehicle?->plate_number ?? 'No vehicle' }}</small>
                        </span>
                        <span class="performance-review-badges">
                            <x-status-badge :status="$incident->type" />
                            <x-status-badge :status="$incident->severity" />
                            <x-status-badge :status="$review->fault_decision" />
                        </span>
                    </a>
                @endif
            @empty
                <div class="empty-state">
                    <strong>No final reviews yet.</strong>
                    <span>Your reviewed incidents will appear here after a monitor submits a final human decision.</span>
                </div>
            @endforelse
        </div>
    </section>

    <section class="dashboard-split-grid">
        <article class="workspace-panel dashboard-panel-fill">
            <div>
                <p class="app-kicker">Score guide</p>
                <h2 class="section-title">How your score changes</h2>
            </div>

            <p class="section-copy">Your score starts at 100 and is recalculated from active final reviews, so review deactivation or reactivation stays consistent.</p>

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

        <article class="workspace-panel dashboard-panel-fill">
            <div>
                <p class="app-kicker">Quick actions</p>
                <h2 class="section-title">Driver workspace</h2>
            </div>

            <div class="quick-action-grid driver-action-grid">
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
        </article>
    </section>
@endsection
