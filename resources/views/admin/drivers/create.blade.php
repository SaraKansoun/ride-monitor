@extends('layouts.app')

@section('title', 'Create Driver')

@section('content')
    <section class="workspace-panel">
        <div class="admin-header">
            <div>
                <p class="app-kicker">Drivers</p>
                <h2 class="section-title">Create driver</h2>
            </div>
            <a class="app-button app-button-muted" href="{{ route('admin.drivers.index') }}">Back</a>
        </div>

        @include('admin.drivers.form', [
            'action' => route('admin.drivers.store'),
            'method' => 'POST',
            'submit' => 'Create driver',
            'showUserStatus' => false,
        ])
    </section>
@endsection
