@extends('layouts.app')

@section('title', 'Driver Performance')

@section('content')
    <section class="dashboard-grid">
        <article class="summary-card">
            <span class="summary-label">Current score</span>
            <strong>{{ $score->score }}</strong>
        </article>

        <article class="summary-card">
            <span class="summary-label">Reviewed incidents</span>
            <strong>{{ $score->total_incidents }}</strong>
        </article>

        <article class="summary-card">
            <span class="summary-label">Unsafe events</span>
            <strong>{{ $score->unsafe_events }}</strong>
        </article>
    </section>

    <section class="workspace-panel">
        <h2>Your Safety Score</h2>
        <p class="section-copy">This score is based on final human reviews for your active incidents. AI observations do not decide your score.</p>

        <dl class="detail-grid">
            <div>
                <dt>Driver</dt>
                <dd>{{ $driver->user?->name ?? 'Driver' }}</dd>
            </div>

            <div>
                <dt>Status</dt>
                <dd><span class="status-badge status-{{ $driver->status }}">{{ str_replace('_', ' ', $driver->status) }}</span></dd>
            </div>

            <div>
                <dt>License number</dt>
                <dd>{{ $driver->license_number }}</dd>
            </div>

            <div>
                <dt>Last updated</dt>
                <dd>{{ $score->last_updated_at?->format('Y-m-d H:i') ?? 'Not updated' }}</dd>
            </div>
        </dl>
    </section>
@endsection
