@extends('layouts.app')

@section('title', 'Edit Driver')

@section('content')
    <section class="workspace-panel">
        <div class="admin-header">
            <div>
                <p class="app-kicker">Drivers</p>
                <h2 class="section-title">Edit driver</h2>
            </div>
            <a class="app-button app-button-muted" href="{{ route('admin.drivers.show', $driver) }}">Back</a>
        </div>

        @include('admin.drivers.form', [
            'action' => route('admin.drivers.update', $driver),
            'method' => 'PATCH',
            'submit' => 'Save driver',
            'showUserStatus' => true,
            'user' => $driver->user,
        ])
    </section>
@endsection
