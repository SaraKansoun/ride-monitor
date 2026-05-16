@extends('layouts.app')

@section('title', 'Assign Vehicle')

@section('content')
    <section class="workspace-panel">
        <div class="admin-header">
            <div>
                <p class="app-kicker">Assignments</p>
                <h2 class="section-title">Assign vehicle</h2>
            </div>
            <a class="app-button app-button-muted" href="{{ route('admin.assignments.index') }}">Back</a>
        </div>

        <form class="admin-form" method="POST" action="{{ route('admin.assignments.store') }}">
            @csrf

            <div class="form-grid">
                <label class="form-field" for="driver_id">
                    <span>Driver</span>
                    <select id="driver_id" name="driver_id" required>
                        <option value="">Select driver</option>
                        @foreach ($drivers as $driver)
                            <option value="{{ $driver->id }}" @selected((int) old('driver_id') === $driver->id)>
                                {{ $driver->user->name }} - {{ $driver->license_number }}
                            </option>
                        @endforeach
                    </select>
                    @error('driver_id') <span class="form-error">{{ $message }}</span> @enderror
                </label>

                <label class="form-field" for="vehicle_id">
                    <span>Vehicle</span>
                    <select id="vehicle_id" name="vehicle_id" required>
                        <option value="">Select vehicle</option>
                        @foreach ($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}" @selected((int) old('vehicle_id') === $vehicle->id)>
                                {{ $vehicle->plate_number }} - {{ $vehicle->model }}
                                @if ($vehicle->currentAssignment)
                                    (currently {{ $vehicle->currentAssignment->driver->user->name }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                    @error('vehicle_id') <span class="form-error">{{ $message }}</span> @enderror
                </label>
            </div>

            <div class="form-actions">
                <button class="app-button app-button-primary" type="submit">Assign vehicle</button>
            </div>
        </form>
    </section>
@endsection
