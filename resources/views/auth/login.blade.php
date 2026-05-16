@extends('layouts.app')

@section('title', 'Sign in')

@section('content')
    <section class="auth-panel">
        <div class="auth-card">
            <div class="auth-heading">
                <p class="app-kicker">Fleet safety access</p>
                <h1 class="auth-title">Sign in</h1>
            </div>

            <form class="auth-form" method="POST" action="{{ route('login.store') }}">
                @csrf

                <label class="form-field" for="email">
                    <span>Email</span>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        autocomplete="email"
                        autofocus
                        required
                    >
                    @error('email')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </label>

                <label class="form-field" for="password">
                    <span>Password</span>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        required
                    >
                    @error('password')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </label>

                <label class="form-checkbox" for="remember">
                    <input id="remember" name="remember" type="checkbox" value="1">
                    <span>Remember me</span>
                </label>

                <button class="app-button app-button-primary" type="submit">Sign in</button>
            </form>
        </div>
    </section>
@endsection
