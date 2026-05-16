@extends('layouts.app')

@section('title', 'Vehicle Details')

@section('content')
    <section class="workspace-panel">
        <div class="admin-header">
            <div>
                <p class="app-kicker">Vehicles</p>
                <h2 class="section-title">{{ $vehicle->plate_number }}</h2>
            </div>
            <div class="inline-actions">
                <a href="{{ route('admin.vehicles.edit', $vehicle) }}">Edit</a>
                <a href="{{ route('admin.vehicles.index') }}">Back</a>
            </div>
        </div>

        <dl class="detail-grid">
            <div><dt>Model</dt><dd>{{ $vehicle->model }}</dd></div>
            <div><dt>Year</dt><dd>{{ $vehicle->year ?? 'Not set' }}</dd></div>
            <div><dt>Status</dt><dd><span class="status-badge status-{{ $vehicle->status }}">{{ $vehicle->status }}</span></dd></div>
            <div><dt>Current driver</dt><dd>{{ $vehicle->currentAssignment?->driver?->user?->name ?? 'Unassigned' }}</dd></div>
        </dl>
    </section>

    <section class="workspace-panel">
        <div>
            <p class="app-kicker">History</p>
            <h2 class="section-title">Assignments</h2>
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Driver</th>
                        <th>Assigned</th>
                        <th>Unassigned</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($vehicle->assignments as $assignment)
                        <tr>
                            <td>{{ $assignment->driver->user->name }}</td>
                            <td>{{ $assignment->assigned_at->format('Y-m-d H:i') }}</td>
                            <td>{{ $assignment->unassigned_at?->format('Y-m-d H:i') ?? 'Current' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">No assignment history.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
