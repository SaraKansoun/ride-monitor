@extends('layouts.app')

@section('title', 'AI Analyses')

@section('content')
    <section class="admin-header">
        <div>
            <p class="app-kicker">Advisory only</p>
            <h2 class="section-title">AI Analyses</h2>
        </div>
    </section>

    <nav class="admin-filters" aria-label="AI analysis status filters">
        @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'all' => 'All'] as $value => $label)
            <a class="admin-filter-link @if ($status === $value) is-active @endif" href="{{ route('ai-analyses.index', ['status' => $value]) }}">{{ $label }}</a>
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
                    <th>Status</th>
                    <th>Confidence</th>
                    <th>Summary</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($analyses as $analysis)
                    <tr>
                        <td>#{{ $analysis->incident->id }}</td>
                        <td>{{ str($analysis->incident->description)->limit(80) }}</td>
                        <td>{{ $analysis->incident->driver->user->name }}</td>
                        <td>{{ $analysis->incident->vehicle?->plate_number ?? 'Not selected' }}</td>
                        <td><span class="status-badge status-{{ $analysis->status }}">{{ $analysis->status }}</span></td>
                        <td>{{ $analysis->confidence_score !== null ? number_format($analysis->confidence_score, 2) : 'Pending' }}</td>
                        <td>{{ $analysis->summary ? str($analysis->summary)->limit(80) : 'AI observations are not ready yet.' }}</td>
                        <td>
                            <div class="inline-actions">
                                <a href="{{ route('incidents.show', $analysis->incident) }}">View incident</a>
                                @if ($analysis->is_active)
                                    @can('deactivate', $analysis)
                                        <form method="POST" action="{{ route('ai-analyses.deactivate', $analysis) }}" data-confirm="Deactivate this AI analysis?">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit">Deactivate</button>
                                        </form>
                                    @endcan
                                @else
                                    @can('reactivate', $analysis)
                                        <form method="POST" action="{{ route('ai-analyses.reactivate', $analysis) }}">
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
                        <td colspan="8">No AI analyses found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <div class="admin-pagination">{{ $analyses->links() }}</div>
@endsection
