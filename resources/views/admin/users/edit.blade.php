@extends('layouts.app')

@section('title', 'Edit User')

@section('content')
    <section class="workspace-panel">
        <div class="admin-header">
            <div>
                <p class="app-kicker">Users</p>
                <h2 class="section-title">Edit user</h2>
            </div>
            <a class="app-button app-button-muted" href="{{ route('admin.users.show', $user) }}">Back</a>
        </div>

        @include('admin.users.form', [
            'action' => route('admin.users.update', $user),
            'method' => 'PATCH',
            'submit' => 'Save user',
        ])
    </section>
@endsection
