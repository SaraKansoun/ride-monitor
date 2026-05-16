@extends('layouts.app')

@section('title', 'Create User')

@section('content')
    <section class="workspace-panel">
        <div class="admin-header">
            <div>
                <p class="app-kicker">Users</p>
                <h2 class="section-title">Create user</h2>
            </div>
            <a class="app-button app-button-muted" href="{{ route('admin.users.index') }}">Back</a>
        </div>

        @include('admin.users.form', [
            'action' => route('admin.users.store'),
            'method' => 'POST',
            'submit' => 'Create user',
        ])
    </section>
@endsection
