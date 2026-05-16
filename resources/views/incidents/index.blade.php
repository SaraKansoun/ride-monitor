@extends('layouts.app')

@section('title', 'Incidents')

@section('content')
    <section class="admin-header">
        <div>
            <p class="app-kicker">Safety</p>
            <h2 class="section-title">Incidents</h2>
        </div>
        @can('create', \App\Models\Incident::class)
            <a class="app-button app-button-primary" href="{{ route('incidents.create') }}">Report incident</a>
        @endcan
    </section>

    <nav class="admin-filters" aria-label="Incident status filters">
        @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'all' => 'All'] as $value => $label)
            <a class="admin-filter-link @if ($status === $value) is-active @endif" href="{{ route('incidents.index', ['status' => $value]) }}">{{ $label }}</a>
        @endforeach
    </nav>

    <section class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Incident</th>
                    <th>Description</th>
                    <th>Driver</th>
                    <th>Vehicle</th>
                    <th>Type</th>
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
                        <td><span class="status-badge status-{{ $incident->status }}">{{ str_replace('_', ' ', $incident->status) }}</span></td>
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
                        <td colspan="9">No incidents found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <div class="admin-pagination">{{ $incidents->links() }}</div>
@endsection
