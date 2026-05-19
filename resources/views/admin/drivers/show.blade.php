@extends('layouts.app')

@section('title', 'Driver Details')

@section('content')
    <section class="workspace-panel">
        <div class="admin-header">
            <div>
                <p class="app-kicker">Drivers</p>
                <h2 class="section-title">{{ $driver->user->name }}</h2>
            </div>
            <div class="inline-actions">
                <a href="{{ route('admin.drivers.edit', $driver) }}">Edit</a>
                <a href="{{ route('admin.drivers.index') }}">Back</a>
            </div>
        </div>

        <dl class="detail-grid">
            <div><dt>Email</dt><dd>{{ $driver->user->email }}</dd></div>
            <div><dt>License</dt><dd>{{ $driver->license_number }}</dd></div>
            <div><dt>Phone</dt><dd>{{ $driver->phone ?? 'Not provided' }}</dd></div>
            <div><dt>Status</dt><dd><x-status-badge :status="$driver->status" /></dd></div>
            <div><dt>Current vehicle</dt><dd>{{ $driver->currentAssignment?->vehicle?->plate_number ?? 'Unassigned' }}</dd></div>
        </dl>
    </section>
@endsection
