<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Driver Safety Monitoring System') }}</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-100 text-slate-950 antialiased">
        <div class="@auth app-shell @else app-auth-shell @endauth">
            @auth
                <aside class="app-sidebar">
                    <a class="app-brand" href="{{ route('dashboard') }}">
                        <span class="app-brand-mark">DS</span>
                        <span>
                            <span class="app-brand-title">Driver Safety</span>
                            <span class="app-brand-subtitle">Monitoring System</span>
                        </span>
                    </a>

                    <nav class="app-nav" aria-label="Dashboard">
                        <a class="app-nav-link" href="{{ route('dashboard') }}">Dashboard</a>
                        @canany(['view incidents', 'view own incidents'])
                            <a class="app-nav-link" href="{{ route('incidents.index') }}">Incidents</a>
                        @endcanany

                        @can('view ai analyses')
                            <a class="app-nav-link" href="{{ route('ai-analyses.index') }}">AI Analyses</a>
                        @endcan

                        @can('review incidents')
                            <a class="app-nav-link" href="{{ route('incident-reviews.index') }}">Incident Reviews</a>
                        @endcan

                        @can('view safety scores')
                            <a class="app-nav-link" href="{{ route('safety-scores.index') }}">Safety Scores</a>
                        @endcan

                        @can('view users')
                            <a class="app-nav-link" href="{{ route('admin.users.index') }}">Users</a>
                        @endcan

                        @can('view drivers')
                            <a class="app-nav-link" href="{{ route('admin.drivers.index') }}">Drivers</a>
                        @endcan

                        @can('view vehicles')
                            <a class="app-nav-link" href="{{ route('admin.vehicles.index') }}">Vehicles</a>
                        @endcan

                        @if (auth()->user()?->can('manage drivers') && auth()->user()?->can('manage vehicles'))
                            <a class="app-nav-link" href="{{ route('admin.assignments.index') }}">Assignments</a>
                        @endif

                        @can('view own safety score')
                            <a class="app-nav-link" href="{{ route('driver-performance.show') }}">Driver Performance</a>
                        @endcan
                    </nav>
                </aside>
            @endauth

            <main class="@auth app-main @else app-auth-main @endauth">
                @auth
                    <header class="app-topbar">
                        <div>
                            <p class="app-kicker">Signed in</p>
                            <h1 class="app-page-title">@yield('title', 'Dashboard')</h1>
                        </div>

                        <div class="app-userbar">
                            <span>{{ auth()->user()->name }}</span>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="app-button app-button-muted" type="submit">Log out</button>
                            </form>
                        </div>
                    </header>
                @endauth

                @if (session('status'))
                    <div class="flash-message flash-message-success">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="flash-message flash-message-error">
                        {{ $errors->first() }}
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </body>
</html>
