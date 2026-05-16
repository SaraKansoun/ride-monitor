@extends('layouts.app')

@section('title', 'Assignments')

@section('content')
    <section class="admin-header">
        <div>
            <p class="app-kicker">Admin</p>
            <h2 class="section-title">Assignments</h2>
        </div>
        <a class="app-button app-button-primary" href="{{ route('admin.assignments.create') }}">Assign vehicle</a>
    </section>

    <section class="workspace-panel">
        <div>
            <p class="app-kicker">Current</p>
            <h2 class="section-title">Active assignments</h2>
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Vehicle</th>
                        <th>Driver</th>
                        <th>Assigned</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($assignments as $assignment)
                        <tr>
                            <td>{{ $assignment->vehicle->plate_number }}</td>
                            <td>{{ $assignment->driver->user->name }}</td>
                            <td>{{ $assignment->assigned_at->format('Y-m-d H:i') }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.assignments.unassign', $assignment) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="table-button" type="submit">Unassign</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">No active assignments.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="workspace-panel">
        <div>
            <p class="app-kicker">History</p>
            <h2 class="section-title">Recent assignments</h2>
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Vehicle</th>
                        <th>Driver</th>
                        <th>Assigned</th>
                        <th>Unassigned</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($history as $assignment)
                        <tr>
                            <td>{{ $assignment->vehicle->plate_number }}</td>
                            <td>{{ $assignment->driver->user->name }}</td>
                            <td>{{ $assignment->assigned_at->format('Y-m-d H:i') }}</td>
                            <td>{{ $assignment->unassigned_at?->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">No assignment history yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
