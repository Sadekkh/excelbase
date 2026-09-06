@extends('layouts.app')
@section('title', $workspace->name.' | Baserow')
@section('content')
<div class="home home--saas">
    @include('partials.workspace-nav')
    <main class="home__main">
        <header class="home__head">
            <div>
                <p class="home__crumb">Using {{ $workspace->name }} · {{ \App\Support\Access::label($role) }}</p>
                <h1>{{ $dashboard->name ?? 'Workspace home' }}</h1>
                <p>{{ $dashboard->description ?? 'Dashboards and tables your builders published for daily work. You cannot change the structure from here.' }}</p>
            </div>
        </header>
        @include('partials.widgets')
        <h2 class="section-title">Tables</h2>
        <div class="card-grid">
            @foreach ($workspace->databases as $database)
                @foreach ($database->tables as $tbl)
                    <a class="entity-card" href="{{ route('tables.show', $tbl) }}">
                        <div class="entity-card__icon">@include('partials.icon', ['name' => 'table', 'size' => 20])</div>
                        <div class="entity-card__body">
                            <h3>{{ $tbl->name }}</h3>
                            <p>{{ $database->name }}</p>
                        </div>
                    </a>
                @endforeach
            @endforeach
        </div>
    </main>
</div>
@endsection
