@extends('layouts.app')

@section('title', 'Create Vehicle')

@section('content')
    <section class="workspace-panel">
        <div class="admin-header">
            <div>
                <p class="app-kicker">Vehicles</p>
                <h2 class="section-title">Create vehicle</h2>
            </div>
            <a class="app-button app-button-muted" href="{{ route('admin.vehicles.index') }}">Back</a>
        </div>

        @include('admin.vehicles.form', [
            'action' => route('admin.vehicles.store'),
            'method' => 'POST',
            'submit' => 'Create vehicle',
        ])
    </section>
@endsection
