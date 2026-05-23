<form class="admin-form" method="POST" action="{{ $action }}" enctype="multipart/form-data" data-incident-upload-form>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="form-grid">
        <label class="form-field">
            Incident type
            <select name="type" required>
                @foreach ($types as $type)
                    <option value="{{ $type }}" @selected(old('type', $incident->type) === $type)>{{ str_replace('_', ' ', $type) }}</option>
                @endforeach
            </select>
            @error('type')
                <span class="form-error">{{ $message }}</span>
            @enderror
        </label>

        <label class="form-field">
            Assigned vehicle
            <select name="vehicle_id">
                <option value="">No vehicle selected</option>
                @foreach ($assignedVehicles as $vehicle)
                    <option value="{{ $vehicle->id }}" @selected((string) old('vehicle_id', $incident->vehicle_id) === (string) $vehicle->id)>{{ $vehicle->plate_number }} - {{ $vehicle->model }}</option>
                @endforeach
            </select>
            @error('vehicle_id')
                <span class="form-error">{{ $message }}</span>
            @enderror
        </label>
    </div>

    <label class="form-field">
        Description
        <textarea name="description" required>{{ old('description', $incident->description) }}</textarea>
        @error('description')
            <span class="form-error">{{ $message }}</span>
        @enderror
    </label>

    <label class="form-field">
        Media files
        <input name="media[]" type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.mp4,.mov,.avi,image/jpeg,image/png,image/webp,application/pdf,video/mp4,video/quicktime,video/x-msvideo">
        <span class="form-help">Upload up to 5 files. Images, PDFs, and common video files are accepted up to 20 MB each.</span>
        @error('media')
            <span class="form-error">{{ $message }}</span>
        @enderror
        @error('media.*')
            <span class="form-error">{{ $message }}</span>
        @enderror
    </label>

    <div class="form-actions">
        <button class="app-button app-button-primary" type="submit">{{ $submit }}</button>
        <a class="app-button app-button-muted" href="{{ route('incidents.index') }}">Cancel</a>
    </div>

    <p class="upload-progress-note" data-upload-status>
        Uploading dashcam media and preparing AI processing...
    </p>
</form>
