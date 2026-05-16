@extends('layouts.app')

@section('title', 'Report Incident')

@section('content')
    <section class="workspace-panel">
        <div class="admin-header">
            <div>
                <p class="app-kicker">Incidents</p>
                <h2 class="section-title">Report incident</h2>
            </div>
            <a class="app-button app-button-muted" href="{{ route('incidents.index') }}">Back</a>
        </div>

        @include('incidents.form', [
            'action' => route('incidents.store'),
            'assignedVehicles' => $assignedVehicles,
            'incident' => $incident,
            'method' => 'POST',
            'submit' => 'Submit incident',
            'types' => $types,
        ])
    </section>
@endsection
