@extends('layouts.app')

@section('title', 'Users')

@section('content')
    <section class="admin-header">
        <div>
            <p class="app-kicker">Admin</p>
            <h2 class="section-title">Users</h2>
        </div>
        @can('create', \App\Models\User::class)
            <a class="app-button app-button-primary" href="{{ route('admin.users.create') }}">Create user</a>
        @endcan
    </section>

    <nav class="admin-filters" aria-label="User status filters">
        @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'all' => 'All'] as $value => $label)
            <a class="admin-filter-link @if ($status === $value) is-active @endif" href="{{ route('admin.users.index', array_merge(request()->except('page'), ['status' => $value])) }}">{{ $label }}</a>
        @endforeach
    </nav>

    <form class="filter-panel" method="GET" action="{{ route('admin.users.index') }}" data-auto-filter>
        <input type="hidden" name="status" value="{{ $status }}">

        <label class="filter-field">
            <span>Search users</span>
            <input type="search" name="q" value="{{ $search }}" placeholder="Name or email">
        </label>

        <label class="filter-field">
            <span>Role</span>
            <select name="role">
                @foreach ($roles as $roleOption)
                    <option value="{{ $roleOption }}" @selected($role === $roleOption)>{{ str($roleOption)->headline() }}</option>
                @endforeach
            </select>
        </label>

        <div class="filter-actions">
            <a class="app-button app-button-muted" href="{{ route('admin.users.index') }}">Reset</a>
        </div>
    </form>

    <section class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Driver profile</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->getRoleNames()->implode(', ') }}</td>
                        <td><x-status-badge :status="$user->status" /></td>
                        <td>
                            @if ($user->hasRole('driver'))
                                @if ($user->driverProfile)
                                    <span class="status-badge status-active">Complete</span>
                                @else
                                    <span class="status-badge status-warning">Missing profile</span>
                                @endif
                            @else
                                <span class="muted-text">Not a driver</span>
                            @endif
                        </td>
                        <td>
                            <div class="inline-actions">
                                <a href="{{ route('admin.users.show', $user) }}">View</a>
                                @can('update', $user)
                                    <a href="{{ route('admin.users.edit', $user) }}">Edit</a>
                                @endcan
                                @if ($user->status === \App\Models\User::STATUS_ACTIVE)
                                    @can('deactivate', $user)
                                        <form method="POST" action="{{ route('admin.users.deactivate', $user) }}" data-confirm="Deactivate this user? They will no longer be able to sign in.">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit">Deactivate</button>
                                        </form>
                                    @endcan
                                @else
                                    @can('reactivate', $user)
                                        <form method="POST" action="{{ route('admin.users.reactivate', $user) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit">Reactivate</button>
                                        </form>
                                    @endcan
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">No users found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <div class="admin-pagination">{{ $users->links() }}</div>
@endsection
