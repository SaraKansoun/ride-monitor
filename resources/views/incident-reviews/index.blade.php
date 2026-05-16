@extends('layouts.app')

@section('title', 'Incident Reviews')

@section('content')
    <section class="admin-header">
        <div>
            <p class="app-kicker">Monitor</p>
            <h2 class="section-title">Incident Reviews</h2>
        </div>
    </section>

    <nav class="admin-filters" aria-label="Incident review tabs">
        <a class="admin-filter-link @if ($tab === 'pending') is-active @endif" href="{{ route('incident-reviews.index', ['tab' => 'pending']) }}">Pending Reviews</a>
        <a class="admin-filter-link @if ($tab === 'reviews') is-active @endif" href="{{ route('incident-reviews.index', ['tab' => 'reviews']) }}">Incident Reviews</a>
    </nav>

    @if ($tab === 'pending')
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
                    @forelse ($pendingIncidents as $incident)
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
                                    <a href="{{ route('incidents.show', $incident) }}">Open</a>
                                    @if ($incident->status === \App\Models\Incident::STATUS_PENDING)
                                        <form method="POST" action="{{ route('incidents.review.start', $incident) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit">Start review</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">No pending reviews found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <div class="admin-pagination">{{ $pendingIncidents->links() }}</div>
    @else
        <nav class="admin-filters" aria-label="Review status filters">
            @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'all' => 'All'] as $value => $label)
                <a class="admin-filter-link @if ($reviewStatus === $value) is-active @endif" href="{{ route('incident-reviews.index', ['tab' => 'reviews', 'status' => $value]) }}">{{ $label }}</a>
            @endforeach
        </nav>

        <section class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Incident</th>
                        <th>Driver</th>
                        <th>Vehicle</th>
                        <th>Decision</th>
                        <th>Status</th>
                        <th>Reviewed by</th>
                        <th>Reviewed</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reviews as $review)
                        <tr>
                            <td>#{{ $review->incident->id }}</td>
                            <td>{{ $review->incident->driver->user->name }}</td>
                            <td>{{ $review->incident->vehicle?->plate_number ?? 'Not selected' }}</td>
                            <td>{{ str_replace('_', ' ', $review->fault_decision) }}</td>
                            <td><span class="status-badge @if ($review->is_active) status-active @else status-inactive @endif">{{ $review->is_active ? 'active' : 'inactive' }}</span></td>
                            <td>{{ $review->reviewer->name }}</td>
                            <td>{{ $review->reviewed_at->format('Y-m-d H:i') }}</td>
                            <td>
                                <div class="inline-actions">
                                    <a href="{{ route('incidents.show', $review->incident) }}">View incident</a>
                                    @if ($review->is_active)
                                        @can('deactivate', $review)
                                            <form method="POST" action="{{ route('incident-reviews.deactivate', $review) }}" data-confirm="Deactivate this final review?">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit">Deactivate</button>
                                            </form>
                                        @endcan
                                    @else
                                        @can('reactivate', $review)
                                            <form method="POST" action="{{ route('incident-reviews.reactivate', $review) }}">
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
                            <td colspan="8">No incident reviews found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <div class="admin-pagination">{{ $reviews->links() }}</div>
    @endif
@endsection
