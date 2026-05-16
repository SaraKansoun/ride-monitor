@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <section class="dashboard-grid">
        <article class="summary-card">
            <span class="summary-label">Current role</span>
            <strong>{{ auth()->user()->getRoleNames()->first() ?? 'Unassigned' }}</strong>
        </article>

        <article class="summary-card">
            <span class="summary-label">Open incidents</span>
            <strong>0</strong>
        </article>

        <article class="summary-card">
            <span class="summary-label">Pending reviews</span>
            <strong>0</strong>
        </article>
    </section>

    <section class="workspace-panel">
        <div>
            <p class="app-kicker">Operations</p>
            <h2 class="section-title">Safety command center</h2>
        </div>

        <div class="workspace-actions">
            @role('admin')
                <a class="workspace-link" href="{{ route('dashboard.admin') }}">Admin workspace</a>
            @endrole

            @role('monitor')
                <a class="workspace-link" href="{{ route('dashboard.monitor') }}">Monitor workspace</a>
            @endrole

            @role('driver')
                <a class="workspace-link" href="{{ route('dashboard.driver') }}">Driver workspace</a>
            @endrole
        </div>
    </section>
@endsection
