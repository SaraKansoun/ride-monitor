@extends('layouts.app')

@section('title', 'Driver workspace')

@section('content')
    <section class="dashboard-grid">
        @foreach ($metrics as $metric)
            <article class="summary-card">
                <span class="summary-label">{{ $metric['label'] }}</span>
                <strong>{{ $metric['value'] }}</strong>
            </article>
        @endforeach
    </section>

    <section class="workspace-panel">
        <div>
            <p class="app-kicker">Driver dashboard</p>
            <h2 class="section-title">Driver workspace</h2>
        </div>

        @if ($driver)
            <p class="section-copy">Your dashboard shows only your incidents and current safety score.</p>

            <dl class="detail-grid">
                <div>
                    <dt>Driver</dt>
                    <dd>{{ $driver->user?->name ?? 'Driver' }}</dd>
                </div>

                <div>
                    <dt>Driver status</dt>
                    <dd><span class="status-badge status-{{ $driver->status }}">{{ str_replace('_', ' ', $driver->status) }}</span></dd>
                </div>

                <div>
                    <dt>Latest incident status</dt>
                    <dd>
                        @if ($latestIncident)
                            <span class="status-badge status-{{ $latestIncident->status }}">{{ str_replace('_', ' ', $latestIncident->status) }}</span>
                        @else
                            No active incidents yet.
                        @endif
                    </dd>
                </div>

                <div>
                    <dt>Latest incident</dt>
                    <dd>
                        @if ($latestIncident)
                            <a href="{{ route('incidents.show', $latestIncident) }}">{{ $latestIncident->description }}</a>
                        @else
                            No active incidents yet.
                        @endif
                    </dd>
                </div>
            </dl>
        @else
            <p class="section-copy">No driver profile is linked to your account yet. Dashboard counts will appear after an admin creates your driver profile.</p>
        @endif
    </section>
@endsection
