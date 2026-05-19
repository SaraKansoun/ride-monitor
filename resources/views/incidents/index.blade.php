@extends('layouts.app')

@section('title', 'Incidents')

@section('content')
    <section class="admin-header">
        <div>
            <p class="app-kicker">Safety</p>
            <h2 class="section-title">Incidents</h2>
        </div>
        <div class="page-actions">
            <a class="app-button app-button-muted" href="{{ route('incidents.export', request()->query()) }}">Export CSV</a>
            @can('create', \App\Models\Incident::class)
                <a class="app-button app-button-primary" href="{{ route('incidents.create') }}">Report incident</a>
            @endcan
        </div>
    </section>

    <nav class="admin-filters" aria-label="Incident status filters">
        @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'all' => 'All'] as $value => $label)
            <a class="admin-filter-link @if ($status === $value) is-active @endif" href="{{ route('incidents.index', array_merge(request()->except('page'), ['status' => $value])) }}">{{ $label }}</a>
        @endforeach
    </nav>

    <form class="filter-panel filter-panel-wide" method="GET" action="{{ route('incidents.index') }}" data-auto-filter>
        <input type="hidden" name="status" value="{{ $status }}">

        <label class="filter-field">
            <span>Search incidents</span>
            <input type="search" name="q" value="{{ $search }}" placeholder="Description, driver, vehicle">
        </label>

        <label class="filter-field">
            <span>Type</span>
            <select name="type">
                <option value="all" @selected($type === 'all')>All types</option>
                @foreach ($types as $typeOption)
                    <option value="{{ $typeOption }}" @selected($type === $typeOption)>{{ str($typeOption)->headline() }}</option>
                @endforeach
            </select>
        </label>

        <label class="filter-field">
            <span>Severity</span>
            <select name="severity">
                <option value="all" @selected($severity === 'all')>All severity</option>
                @foreach ($severities as $severityOption)
                    <option value="{{ $severityOption }}" @selected($severity === $severityOption)>{{ str($severityOption)->headline() }}</option>
                @endforeach
            </select>
        </label>

        <label class="filter-field">
            <span>Incident status</span>
            <select name="incident_status">
                <option value="all" @selected($workflowStatus === 'all')>All statuses</option>
                @foreach (\App\Models\Incident::STATUSES as $statusOption)
                    <option value="{{ $statusOption }}" @selected($workflowStatus === $statusOption)>{{ str($statusOption)->headline() }}</option>
                @endforeach
            </select>
        </label>

        <label class="filter-field">
            <span>From</span>
            <input type="date" name="reported_from" value="{{ $reportedFrom }}">
        </label>

        <label class="filter-field">
            <span>To</span>
            <input type="date" name="reported_to" value="{{ $reportedTo }}">
        </label>

        <div class="filter-actions">
            <a class="app-button app-button-muted" href="{{ route('incidents.index') }}">Reset</a>
        </div>
    </form>

    <section class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Incident</th>
                    <th>Description</th>
                    <th>Driver</th>
                    <th>Vehicle</th>
                    <th>Type</th>
                    <th>Severity</th>
                    <th>Status</th>
                    <th>Media</th>
                    <th>Reported</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($incidents as $incident)
                    <tr>
                        <td>#{{ $incident->id }}</td>
                        <td>{{ str($incident->description)->limit(80) }}</td>
                        <td>{{ $incident->driver->user->name }}</td>
                        <td>{{ $incident->vehicle?->plate_number ?? 'Not selected' }}</td>
                        <td>{{ str_replace('_', ' ', $incident->type) }}</td>
                        <td><x-status-badge :status="$incident->severity" /></td>
                        <td><x-status-badge :status="$incident->status" /></td>
                        <td>{{ $incident->media_count }}</td>
                        <td>{{ $incident->created_at->format('Y-m-d H:i') }}</td>
                        <td>
                            <div class="inline-actions">
                                <a href="{{ route('incidents.show', $incident) }}">View</a>
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
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10">No incidents found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <div class="admin-pagination">{{ $incidents->links() }}</div>
@endsection
