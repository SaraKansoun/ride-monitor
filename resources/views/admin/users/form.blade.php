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

        <label class="form-field" for="role">
            <span>Role</span>
            <select id="role" name="role" required>
                @foreach ($roles as $role)
                    <option value="{{ $role }}" @selected(old('role', $user->getRoleNames()->first()) === $role)>{{ ucfirst($role) }}</option>
                @endforeach
            </select>
            @error('role') <span class="form-error">{{ $message }}</span> @enderror
        </label>

        <label class="form-field" for="status">
            <span>Status</span>
            <select id="status" name="status" required>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(old('status', $user->status) === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
            @error('status') <span class="form-error">{{ $message }}</span> @enderror
        </label>
    </div>

    <div class="form-actions">
        <button class="app-button app-button-primary" type="submit">{{ $submit }}</button>
    </div>
</form>
