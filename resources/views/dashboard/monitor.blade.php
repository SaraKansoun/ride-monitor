@extends('layouts.app')

@section('title', 'Monitor workspace')

@section('content')
    <section class="dashboard-grid">
        @foreach ($metrics as $metric)
            <article class="summary-card">
                <span class="summary-label">{{ $metric['label'] }}</span>
                <strong>{{ $metric['value'] }}</strong>
            </article>
        @endforeach
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
                            <td><span class="status-badge status-{{ $incident->status }}">{{ str_replace('_', ' ', $incident->status) }}</span></td>
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
                            <td><span class="status-badge status-{{ $driver->status }}">{{ str_replace('_', ' ', $driver->status) }}</span></td>
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
