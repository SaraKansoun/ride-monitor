@extends('layouts.app')

@section('title', 'User Details')

@section('content')
    <section class="workspace-panel">
        <div class="admin-header">
            <div>
                <p class="app-kicker">Users</p>
                <h2 class="section-title">{{ $user->name }}</h2>
            </div>
            <div class="inline-actions">
                <a href="{{ route('admin.users.edit', $user) }}">Edit</a>
                <a href="{{ route('admin.users.index') }}">Back</a>
            </div>
        </div>

        <dl class="detail-grid">
            <div><dt>Email</dt><dd>{{ $user->email }}</dd></div>
            <div><dt>Role</dt><dd>{{ $user->getRoleNames()->implode(', ') }}</dd></div>
            <div><dt>Status</dt><dd><x-status-badge :status="$user->status" /></dd></div>
            <div>
                <dt>Driver profile</dt>
                <dd>
                    @if ($user->driverProfile)
                        <a href="{{ route('admin.drivers.show', $user->driverProfile) }}">View profile</a>
                    @elseif ($user->hasRole('driver'))
                        <a href="{{ route('admin.drivers.complete', $user) }}">Complete profile</a>
                    @else
                        Not a driver
                    @endif
                </dd>
            </div>
        </dl>
    </section>
@endsection
