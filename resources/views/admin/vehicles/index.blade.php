@extends('layouts.app')

@section('title', 'Vehicles')

@section('content')
    <section class="admin-header">
        <div>
            <p class="app-kicker">Admin</p>
            <h2 class="section-title">Vehicles</h2>
        </div>
        <div class="page-actions">
            <a class="app-button app-button-muted" href="{{ route('admin.vehicles.export', request()->query()) }}">Export CSV</a>
            @can('create', \App\Models\Vehicle::class)
                <a class="app-button app-button-primary" href="{{ route('admin.vehicles.create') }}">Create vehicle</a>
            @endcan
        </div>
    </section>

    <nav class="admin-filters" aria-label="Vehicle status filters">
        @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'all' => 'All'] as $value => $label)
            <a class="admin-filter-link @if ($status === $value) is-active @endif" href="{{ route('admin.vehicles.index', array_merge(request()->except('page'), ['status' => $value])) }}">{{ $label }}</a>
        @endforeach
    </nav>

    <form class="filter-panel" method="GET" action="{{ route('admin.vehicles.index') }}" data-auto-filter>
        <input type="hidden" name="status" value="{{ $status }}">

        <label class="filter-field">
            <span>Search vehicles</span>
            <input type="search" name="q" value="{{ $search }}" placeholder="Plate, model, or year">
        </label>

        <label class="filter-field">
            <span>Assignment</span>
            <select name="assignment">
                @foreach (['all' => 'All vehicles', 'assigned' => 'Assigned', 'unassigned' => 'Unassigned'] as $value => $label)
                    <option value="{{ $value }}" @selected($assignment === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <div class="filter-actions">
            <a class="app-button app-button-muted" href="{{ route('admin.vehicles.index') }}">Reset</a>
        </div>
    </form>

    <section class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Plate</th>
                    <th>Model</th>
                    <th>Year</th>
                    <th>Status</th>
                    <th>Assigned driver</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($vehicles as $vehicle)
                    <tr>
                        <td>{{ $vehicle->plate_number }}</td>
                        <td>{{ $vehicle->model }}</td>
                        <td>{{ $vehicle->year ?? 'Not set' }}</td>
                        <td><x-status-badge :status="$vehicle->status" /></td>
                        <td>{{ $vehicle->currentAssignment?->driver?->user?->name ?? 'Unassigned' }}</td>
                        <td>
                            <div class="inline-actions">
                                <a href="{{ route('admin.vehicles.show', $vehicle) }}">View</a>
                                @can('update', $vehicle)
                                    <a href="{{ route('admin.vehicles.edit', $vehicle) }}">Edit</a>
                                @endcan
                                @if ($vehicle->status === \App\Models\Vehicle::STATUS_ACTIVE)
                                    @can('deactivate', $vehicle)
                                        <form method="POST" action="{{ route('admin.vehicles.deactivate', $vehicle) }}" data-confirm="Deactivate this vehicle?">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit">Deactivate</button>
                                        </form>
                                    @endcan
                                @else
                                    @can('reactivate', $vehicle)
                                        <form method="POST" action="{{ route('admin.vehicles.reactivate', $vehicle) }}">
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
                        <td colspan="6">No vehicles found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <div class="admin-pagination">{{ $vehicles->links() }}</div>
@endsection
