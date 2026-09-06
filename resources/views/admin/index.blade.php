@extends('layouts.app')
@section('title', 'Platform admin | Baserow')
@section('content')
<div class="home home--saas">
    @include('partials.admin-nav')
    <main class="home__main">
        <header class="home__head">
            <div>
                <h1>Platform</h1>
                <p>Every client is a workspace. Assign plans and admins from here.</p>
            </div>
        </header>
        <div class="widget-grid">
            <article class="widget"><h3>Workspaces</h3><p class="widget__value">{{ $stats['workspaces'] }}</p></article>
            <article class="widget"><h3>Users</h3><p class="widget__value">{{ $stats['users'] }}</p></article>
            <article class="widget"><h3>Plans</h3><p class="widget__value">{{ $stats['plans'] }}</p></article>
        </div>
        <h2 class="section-title">Recent workspaces</h2>
        <ul class="plain-list">
            @foreach ($workspaces as $ws)
                <li><a href="{{ route('workspaces.show', $ws) }}">{{ $ws->name }}</a> · {{ $ws->plan?->name ?? 'No plan' }} · {{ $ws->members->count() }} people</li>
            @endforeach
        </ul>
    </main>
</div>
@endsection
