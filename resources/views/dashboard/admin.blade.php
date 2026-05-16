@extends('layouts.app')

@section('title', 'Admin workspace')

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
            <p class="app-kicker">Admin dashboard</p>
            <h2 class="section-title">Admin workspace</h2>
        </div>

        <p class="section-copy">Review active system totals and jump into the core administration modules.</p>

        <div class="workspace-actions">
            <a class="workspace-link" href="{{ route('admin.users.index') }}">Manage users</a>
            <a class="workspace-link" href="{{ route('admin.drivers.index') }}">Manage drivers</a>
            <a class="workspace-link" href="{{ route('admin.vehicles.index') }}">Manage vehicles</a>
            <a class="workspace-link" href="{{ route('incidents.index') }}">View incidents</a>
        </div>
    </section>
@endsection
