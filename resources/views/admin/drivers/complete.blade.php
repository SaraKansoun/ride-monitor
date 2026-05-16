@extends('layouts.app')

@section('title', 'Complete Driver Profile')

@section('content')
    <section class="workspace-panel">
        <div class="admin-header">
            <div>
                <p class="app-kicker">Drivers</p>
                <h2 class="section-title">Complete profile for {{ $user->name }}</h2>
            </div>
            <a class="app-button app-button-muted" href="{{ route('admin.drivers.index') }}">Back</a>
        </div>

        <form class="admin-form" method="POST" action="{{ route('admin.drivers.complete.store', $user) }}">
            @csrf
            <div class="form-grid">
                <label class="form-field" for="license_number">
                    <span>License number</span>
                    <input id="license_number" name="license_number" value="{{ old('license_number') }}" required>
                    @error('license_number') <span class="form-error">{{ $message }}</span> @enderror
                </label>

                <label class="form-field" for="phone">
                    <span>Phone</span>
                    <input id="phone" name="phone" value="{{ old('phone') }}">
                    @error('phone') <span class="form-error">{{ $message }}</span> @enderror
                </label>

                <label class="form-field" for="status">
                    <span>Status</span>
                    <select id="status" name="status" required>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected(old('status', $driver->status) === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                    @error('status') <span class="form-error">{{ $message }}</span> @enderror
                </label>
            </div>

            <div class="form-actions">
                <button class="app-button app-button-primary" type="submit">Complete profile</button>
            </div>
        </form>
    </section>
@endsection
