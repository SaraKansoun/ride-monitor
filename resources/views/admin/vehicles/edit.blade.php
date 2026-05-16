@extends('layouts.app')

@section('title', 'Edit Vehicle')

@section('content')
    <section class="workspace-panel">
        <div class="admin-header">
            <div>
                <p class="app-kicker">Vehicles</p>
                <h2 class="section-title">Edit vehicle</h2>
            </div>
            <a class="app-button app-button-muted" href="{{ route('admin.vehicles.show', $vehicle) }}">Back</a>
        </div>

        @include('admin.vehicles.form', [
            'action' => route('admin.vehicles.update', $vehicle),
            'method' => 'PATCH',
            'submit' => 'Save vehicle',
        ])
    </section>
@endsection
