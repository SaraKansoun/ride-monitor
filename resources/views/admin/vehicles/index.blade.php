@extends('layouts.app')

@section('title', 'Vehicles')

@section('content')
    <section class="admin-header">
        <div>
            <p class="app-kicker">Admin</p>
            <h2 class="section-title">Vehicles</h2>
        </div>
        @can('create', \App\Models\Vehicle::class)
            <a class="app-button app-button-primary" href="{{ route('admin.vehicles.create') }}">Create vehicle</a>
        @endcan
    </section>

    <nav class="admin-filters" aria-label="Vehicle status filters">
        @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'all' => 'All'] as $value => $label)
            <a class="admin-filter-link @if ($status === $value) is-active @endif" href="{{ route('admin.vehicles.index', ['status' => $value]) }}">{{ $label }}</a>
        @endforeach
    </nav>

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
                        <td><span class="status-badge status-{{ $vehicle->status }}">{{ $vehicle->status }}</span></td>
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
