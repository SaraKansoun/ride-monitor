<form class="admin-form" method="POST" action="{{ $action }}">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="form-grid">
        <label class="form-field" for="plate_number">
            <span>Plate number</span>
            <input id="plate_number" name="plate_number" value="{{ old('plate_number', $vehicle->plate_number) }}" required>
            @error('plate_number') <span class="form-error">{{ $message }}</span> @enderror
        </label>

        <label class="form-field" for="model">
            <span>Model</span>
            <input id="model" name="model" value="{{ old('model', $vehicle->model) }}" required>
            @error('model') <span class="form-error">{{ $message }}</span> @enderror
        </label>

        <label class="form-field" for="year">
            <span>Year</span>
            <input id="year" name="year" type="number" value="{{ old('year', $vehicle->year) }}">
            @error('year') <span class="form-error">{{ $message }}</span> @enderror
        </label>

        <label class="form-field" for="status">
            <span>Status</span>
            <select id="status" name="status" required>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(old('status', $vehicle->status) === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
            @error('status') <span class="form-error">{{ $message }}</span> @enderror
        </label>
    </div>

    <div class="form-actions">
        <button class="app-button app-button-primary" type="submit">{{ $submit }}</button>
    </div>
</form>
