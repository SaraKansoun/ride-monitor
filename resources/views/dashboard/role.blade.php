@extends('layouts.app')

@section('title', $title)

@section('content')
    <section class="workspace-panel">
        <div>
            <p class="app-kicker">Workspace</p>
            <h2 class="section-title">{{ $title }}</h2>
        </div>

        <p class="section-copy">{{ $description }}</p>
    </section>
@endsection
