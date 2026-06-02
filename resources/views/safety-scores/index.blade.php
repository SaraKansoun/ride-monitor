@extends('layouts.app')

@section('title', 'Safety Scores')

@section('content')
    <section class="workspace-panel">
        <div class="admin-header">
            <div>
                <p class="app-kicker">Safety</p>
                <h2 class="section-title">Safety Scores</h2>
            </div>
            <a class="app-button app-button-muted" href="{{ route('safety-scores.export', request()->query()) }}">Export CSV</a>
        </div>

        <p class="section-copy safety-scores-copy">Scores update after final human reviews, while inactive score records remain available through the filters.</p>

        <div class="admin-filters">
            @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'all' => 'All'] as $filter => $label)
                <a class="admin-filter-link @if ($status === $filter) is-active @endif" href="{{ route('safety-scores.index', array_merge(request()->except('page'), ['status' => $filter])) }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <form class="filter-panel" method="GET" action="{{ route('safety-scores.index') }}" data-auto-filter>
            <input type="hidden" name="status" value="{{ $status }}">

            <label class="filter-field">
                <span>Search drivers</span>
                <input type="search" name="q" value="{{ $search }}" placeholder="Name, email, license, phone">
            </label>

            <label class="filter-field">
                <span>Score band</span>
                <select name="score_band">
                    @foreach (['all' => 'All scores', 'strong' => 'Strong 80-100', 'attention' => 'Needs attention 50-79', 'risk' => 'High risk below 50'] as $value => $label)
                        <option value="{{ $value }}" @selected($scoreBand === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <div class="filter-actions">
                <a class="app-button app-button-muted" href="{{ route('safety-scores.index') }}">Reset</a>
            </div>
        </form>

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
                                <x-status-badge :status="$driver->status" />
                            </td>
                            <td>{{ $score?->total_incidents ?? 0 }}</td>
                            <td>{{ $score?->unsafe_events ?? 0 }}</td>
                            <td>{{ $score?->score ?? 100 }}</td>
                            <td>
                                <x-status-badge :status="$score?->is_active ? 'active' : 'inactive'" />
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
