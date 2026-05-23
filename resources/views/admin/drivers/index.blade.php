@extends('layouts.app')

@section('title', 'Drivers')

@section('content')
    <section class="admin-header">
        <div>
            <p class="app-kicker">Admin</p>
            <h2 class="section-title">Drivers</h2>
        </div>
        <div class="page-actions">
            <a class="app-button app-button-muted" href="{{ route('admin.drivers.export', request()->query()) }}">Export CSV</a>
        </div>
    </section>

    @can('create', \App\Models\User::class)
        <div class="onboarding-notice">
            <x-nav-icon name="users" class="onboarding-notice-icon" />
            <div class="onboarding-notice-content">
                <strong>Driver onboarding starts in Users</strong>
                <span>To add a new driver, create a user with the driver role from the Users module.</span>
            </div>
        </div>
    @endcan

    <nav class="admin-filters" aria-label="Driver status filters">
        @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'all' => 'All'] as $value => $label)
            <a class="admin-filter-link @if ($status === $value) is-active @endif" href="{{ route('admin.drivers.index', array_merge(request()->except('page'), ['status' => $value])) }}">{{ $label }}</a>
        @endforeach
    </nav>

    <form class="filter-panel" method="GET" action="{{ route('admin.drivers.index') }}" data-auto-filter>
        <input type="hidden" name="status" value="{{ $status }}">

        <label class="filter-field">
            <span>Search drivers</span>
            <input type="search" name="q" value="{{ $search }}" placeholder="Name, email, license, phone">
        </label>

        <label class="filter-field">
            <span>Profile</span>
            <select name="profile">
                @foreach (['all' => 'All profiles', 'complete' => 'Complete profiles', 'missing' => 'Missing profiles'] as $value => $label)
                    <option value="{{ $value }}" @selected($profile === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <div class="filter-actions">
            <a class="app-button app-button-muted" href="{{ route('admin.drivers.index') }}">Reset</a>
        </div>
    </form>

    <section class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>License</th>
                    <th>Status</th>
                    <th>Vehicle</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($drivers as $driverUser)
                    @php
                        $profile = $driverUser->driverProfile;
                        $vehiclePlateNumbers = collect($profile?->currentAssignments ?? [])
                            ->pluck('vehicle.plate_number')
                            ->filter()
                            ->values();
                        $visiblePlateNumbers = $vehiclePlateNumbers->take(2);
                        $hiddenVehicleCount = $vehiclePlateNumbers->count() - $visiblePlateNumbers->count();
                    @endphp
                    <tr>
                        <td>{{ $driverUser->name }}</td>
                        <td>{{ $driverUser->email }}</td>
                        <td>{{ $profile?->license_number ?? 'Missing profile' }}</td>
                        <td>
                            @if ($profile)
                                <x-status-badge :status="$profile->status" />
                            @else
                                <span class="status-badge status-warning">Missing profile</span>
                            @endif
                        </td>
                        <td>
                            @if ($vehiclePlateNumbers->isEmpty())
                                Unassigned
                            @else
                                <div class="vehicle-summary" tabindex="0" data-vehicle-summary aria-label="Assigned vehicles: {{ $vehiclePlateNumbers->join(', ') }}">
                                    <span class="vehicle-summary-text">
                                        {{ $visiblePlateNumbers->join(', ') }}@if ($hiddenVehicleCount > 0), @endif
                                    </span>
                                    @if ($hiddenVehicleCount > 0)
                                        <span class="vehicle-summary-more">+{{ $hiddenVehicleCount }}</span>
                                    @endif
                                    <div class="vehicle-summary-popover" data-vehicle-summary-popover role="tooltip" hidden>
                                        @foreach ($vehiclePlateNumbers as $plateNumber)
                                            <span>{{ $plateNumber }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </td>
                        <td>
                            <div class="inline-actions">
                                @if ($profile)
                                    <a href="{{ route('admin.drivers.show', $profile) }}">View</a>
                                    @can('update', $profile)
                                        <a href="{{ route('admin.drivers.edit', $profile) }}">Edit</a>
                                    @endcan
                                    @if ($profile->status === \App\Models\Driver::STATUS_ACTIVE)
                                        @can('deactivate', $profile)
                                            <form method="POST" action="{{ route('admin.drivers.deactivate', $profile) }}" data-confirm="Deactivate this driver profile?">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit">Deactivate</button>
                                            </form>
                                        @endcan
                                    @else
                                        @can('reactivate', $profile)
                                            <form method="POST" action="{{ route('admin.drivers.reactivate', $profile) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit">Reactivate</button>
                                            </form>
                                        @endcan
                                    @endif
                                @else
                                    @can('create', \App\Models\Driver::class)
                                        <a href="{{ route('admin.drivers.complete', $driverUser) }}">Complete profile</a>
                                    @endcan
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">No drivers found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <div class="admin-pagination">{{ $drivers->links() }}</div>
@endsection
