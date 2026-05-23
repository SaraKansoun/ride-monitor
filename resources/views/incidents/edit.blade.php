@extends('layouts.app')

@section('title', 'Edit Incident Description')

@section('content')
    <section class="workspace-panel">
        <div class="admin-header">
            <div>
                <p class="app-kicker">Incidents</p>
                <h2 class="section-title">Edit description for incident #{{ $incident->id }}</h2>
            </div>
            <a class="app-button app-button-muted" href="{{ route('incidents.show', $incident) }}">Back</a>
        </div>

        <form class="admin-form" method="POST" action="{{ route('incidents.update', $incident) }}">
            @csrf
            @method('PATCH')

            <label class="form-field">
                Description
                <textarea name="description" required>{{ old('description', $incident->description) }}</textarea>
                @error('description')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </label>

            <div class="form-actions">
                <button class="app-button app-button-primary" type="submit">Save description</button>
                <a class="app-button app-button-muted" href="{{ route('incidents.show', $incident) }}">Cancel</a>
            </div>
        </form>
    </section>
@endsection
