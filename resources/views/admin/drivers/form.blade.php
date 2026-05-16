<form class="admin-form" method="POST" action="{{ $action }}">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="form-grid">
        <label class="form-field" for="name">
            <span>Name</span>
            <input id="name" name="name" value="{{ old('name', $user->name) }}" required>
            @error('name') <span class="form-error">{{ $message }}</span> @enderror
        </label>

        <label class="form-field" for="email">
            <span>Email</span>
            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required>
            @error('email') <span class="form-error">{{ $message }}</span> @enderror
        </label>

        <label class="form-field" for="password">
            <span>Password</span>
            <input id="password" name="password" type="password" @if ($method === 'POST') required @endif>
            @error('password') <span class="form-error">{{ $message }}</span> @enderror
        </label>

        @if ($showUserStatus)
            <label class="form-field" for="user_status">
                <span>User status</span>
                <select id="user_status" name="user_status" required>
                    @foreach ($userStatuses as $status)
                        <option value="{{ $status }}" @selected(old('user_status', $user->status) === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                @error('user_status') <span class="form-error">{{ $message }}</span> @enderror
            </label>
        @endif

        <label class="form-field" for="license_number">
            <span>License number</span>
            <input id="license_number" name="license_number" value="{{ old('license_number', $driver->license_number) }}" required>
            @error('license_number') <span class="form-error">{{ $message }}</span> @enderror
        </label>

        <label class="form-field" for="phone">
            <span>Phone</span>
            <input id="phone" name="phone" value="{{ old('phone', $driver->phone) }}">
            @error('phone') <span class="form-error">{{ $message }}</span> @enderror
        </label>

        <label class="form-field" for="status">
            <span>Driver status</span>
            <select id="status" name="status" required>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(old('status', $driver->status) === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
            @error('status') <span class="form-error">{{ $message }}</span> @enderror
        </label>
    </div>

    <div class="form-actions">
        <button class="app-button app-button-primary" type="submit">{{ $submit }}</button>
    </div>
</form>
