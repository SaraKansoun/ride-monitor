@extends('layouts.app')

@section('title', 'Safety Scores')

@section('content')
    <section class="workspace-panel">
        <h2>Safety Scores</h2>
        <p class="section-copy">Scores are recalculated from active final human reviews only. Inactive drivers are hidden from the active list.</p>

        <div class="admin-filters">
            @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'all' => 'All'] as $filter => $label)
                <a class="admin-filter-link @if ($status === $filter) is-active @endif" href="{{ route('safety-scores.index', ['status' => $filter]) }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Driver</th>
                        <th>Status</th>
                        <th>Total incidents</th>
                        <th>Unsafe events</th>
                        <th>Current score</th>
                        <th>Score status</th>
                        <th>Last updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($drivers as $driver)
                        @php($score = $driver->score)
                        <tr>
                            <td>{{ $driver->user?->name ?? 'Unassigned user' }}</td>
                            <td>
                                <span class="status-badge status-{{ $driver->status }}">{{ str_replace('_', ' ', $driver->status) }}</span>
                            </td>
                            <td>{{ $score?->total_incidents ?? 0 }}</td>
                            <td>{{ $score?->unsafe_events ?? 0 }}</td>
                            <td>{{ $score?->score ?? 100 }}</td>
                            <td>
                                <span class="status-badge status-{{ $score?->is_active ? 'active' : 'inactive' }}">{{ $score?->is_active ? 'active' : 'inactive' }}</span>
                            </td>
                            <td>{{ $score?->last_updated_at?->format('Y-m-d H:i') ?? 'Not updated' }}</td>
                            <td>
                                @if ($score)
                                    <div class="inline-actions">
                                        @if ($score->is_active)
                                            @can('deactivate', $score)
                                                <form method="POST" action="{{ route('driver-scores.deactivate', $score) }}" data-confirm="Deactivate this driver score?">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit">Deactivate</button>
                                                </form>
                                            @endcan
                                        @else
                                            @can('reactivate', $score)
                                                <form method="POST" action="{{ route('driver-scores.reactivate', $score) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit">Reactivate</button>
                                                </form>
                                            @endcan
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">No drivers found for this filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="admin-pagination">
            {{ $drivers->links() }}
        </div>
    </section>
@endsection
