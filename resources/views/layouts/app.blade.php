<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Driver Safety Monitoring System') }}</title>
        <link rel="icon" type="image/svg+xml" href="{{ asset('images/project-icon.svg') }}">

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-zinc-100 text-zinc-950 antialiased">
        <div class="@auth app-shell @else app-auth-shell @endauth" @auth data-app-shell @endauth>
            @auth
                <aside class="app-sidebar" id="app-sidebar">
                    <div class="app-sidebar-head">
                        <a class="app-brand" href="{{ route('dashboard') }}">
                            <span class="app-brand-mark">
                                <img src="{{ asset('images/project-icon.svg') }}" alt="">
                            </span>
                            <span>
                                <span class="app-brand-title">Driver Safety</span>
                                <span class="app-brand-subtitle">Monitoring System</span>
                            </span>
                        </a>

                        <button class="app-sidebar-close" type="button" data-sidebar-close aria-label="Close sidebar">Close</button>
                    </div>

                    <nav class="app-nav" aria-label="Dashboard">
                        <a @class(['app-nav-link', 'is-active' => request()->routeIs('dashboard*')]) href="{{ route('dashboard') }}">
                            <x-nav-icon name="dashboard" />
                            Dashboard
                        </a>
                        @canany(['view incidents', 'view own incidents'])
                            <a @class(['app-nav-link', 'is-active' => request()->routeIs('incidents.*')]) href="{{ route('incidents.index') }}">
                                <x-nav-icon name="incidents" />
                                Incidents
                            </a>
                        @endcanany

                        @can('review incidents')
                            <a @class(['app-nav-link', 'is-active' => request()->routeIs('incident-reviews.*')]) href="{{ route('incident-reviews.index') }}">
                                <x-nav-icon name="reviews" />
                                Review Center
                            </a>
                        @endcan

                        @can('view safety scores')
                            <a @class(['app-nav-link', 'is-active' => request()->routeIs('safety-scores.*')]) href="{{ route('safety-scores.index') }}">
                                <x-nav-icon name="safety" />
                                Safety Scores
                            </a>
                        @endcan

                        @can('view users')
                            <a @class(['app-nav-link', 'is-active' => request()->routeIs('admin.users.*')]) href="{{ route('admin.users.index') }}">
                                <x-nav-icon name="users" />
                                Users
                            </a>
                        @endcan

                        @can('view drivers')
                            <a @class(['app-nav-link', 'is-active' => request()->routeIs('admin.drivers.*')]) href="{{ route('admin.drivers.index') }}">
                                <x-nav-icon name="drivers" />
                                Drivers
                            </a>
                        @endcan

                        @can('view vehicles')
                            <a @class(['app-nav-link', 'is-active' => request()->routeIs('admin.vehicles.*')]) href="{{ route('admin.vehicles.index') }}">
                                <x-nav-icon name="vehicles" />
                                Vehicles
                            </a>
                        @endcan

                        @if (auth()->user()?->can('manage drivers') && auth()->user()?->can('manage vehicles'))
                            <a @class(['app-nav-link', 'is-active' => request()->routeIs('admin.assignments.*')]) href="{{ route('admin.assignments.index') }}">
                                <x-nav-icon name="assignments" />
                                Assignments
                            </a>
                        @endif

                        @if (auth()->user()?->hasRole('driver') && auth()->user()?->can('view own safety score'))
                            <a @class(['app-nav-link', 'is-active' => request()->routeIs('driver-performance.*')]) href="{{ route('driver-performance.show') }}">
                                <x-nav-icon name="performance" />
                                Driver Performance
                            </a>
                        @endif
                    </nav>

                    <div class="app-sidebar-card">
                        <span class="app-sidebar-card-kicker">Fleet safety</span>
                        <strong>Human review stays in control.</strong>
                        <span>AI observations support monitors, not final decisions.</span>
                    </div>
                </aside>

                <button class="app-sidebar-overlay" type="button" data-sidebar-close aria-label="Close navigation"></button>
            @endauth

            <main class="@auth app-main @else app-auth-main @endauth">
                @auth
                    <header class="app-topbar">
                        <div class="app-topbar-title">
                            <button class="app-menu-button" type="button" data-sidebar-toggle aria-controls="app-sidebar" aria-expanded="false">
                                <span aria-hidden="true"></span>
                                Menu
                            </button>
                            <div>
                                <p class="app-kicker">Taxi safety operations</p>
                                <h1 class="app-page-title">@yield('title', 'Dashboard')</h1>
                            </div>
                        </div>

                        <div class="app-userbar" data-user-menu>
                            <button class="app-user-menu-button" type="button" data-user-menu-button aria-expanded="false">
                                <span class="app-user-avatar">{{ str(auth()->user()->name)->substr(0, 1)->upper() }}</span>
                                <span>{{ auth()->user()->name }}</span>
                                <span class="app-user-chevron" aria-hidden="true">
                                    <svg viewBox="0 0 20 20" fill="none">
                                        <path d="m5 8 5 5 5-5" />
                                    </svg>
                                </span>
                            </button>

                            <div class="app-user-menu-panel" data-user-menu-panel hidden>
                                <span class="app-user-menu-label">Signed in account</span>
                                <strong>{{ auth()->user()->email }}</strong>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="app-button app-button-muted" type="submit">Log out</button>
                                </form>
                            </div>
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
