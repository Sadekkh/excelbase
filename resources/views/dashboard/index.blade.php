@extends('layouts.app')
@section('title', 'Dashboard | Baserow')
@section('content')
<div class="home">
    @include('partials.topbar', ['pageTitle' => 'All workspaces'])
    <main class="home__main">
        <header class="home__head">
            <div>
                <h1>All workspaces</h1>
                <p>Workspaces group databases, tables, and the people who can see them.</p>
            </div>
            <button type="button" class="btn btn--primary" data-open-modal="create-workspace">
                @include('partials.icon', ['name' => 'plus', 'size' => 16])
                Create workspace
            </button>
        </header>

        @if ($workspaces->isEmpty())
            <div class="empty-state">
                @include('partials.icon', ['name' => 'workspace', 'size' => 28])
                <h2>No workspaces yet</h2>
                <p>Create a workspace to start adding databases and tables.</p>
                <button type="button" class="btn btn--primary" data-open-modal="create-workspace">Create workspace</button>
            </div>
        @else
            <div class="card-grid">
                @foreach ($workspaces as $workspace)
                    <a class="entity-card" href="{{ route('workspaces.show', $workspace) }}">
                        <div class="entity-card__icon entity-card__icon--workspace">{{ strtoupper(substr($workspace->name, 0, 1)) }}</div>
                        <div class="entity-card__body">
                            <h3>{{ $workspace->name }}</h3>
                            <p>{{ $workspace->plan?->name ?? 'Free' }} · {{ $workspace->databases->count() }} {{ \Illuminate\Support\Str::plural('database', $workspace->databases->count()) }} · {{ $workspace->members->count() }} {{ \Illuminate\Support\Str::plural('member', $workspace->members->count()) }}</p>
                        </div>
                        @include('partials.icon', ['name' => 'chevron-right', 'size' => 16])
                    </a>
                @endforeach
            </div>
        @endif
    </main>
</div>

<div class="modal" id="create-workspace" hidden>
    <div class="modal__backdrop" data-close-modal></div>
    <form class="modal__dialog" method="post" action="{{ route('workspaces.store') }}">
        @csrf
        <header class="modal__head">
            <h2>Create workspace</h2>
            <button type="button" class="icon-btn" data-close-modal>@include('partials.icon', ['name' => 'x'])</button>
        </header>
        <div class="modal__body">
            <label class="field">
                <span>Name</span>
                <input type="text" name="name" required placeholder="e.g. Marketing">
            </label>
        </div>
        <footer class="modal__foot">
            <button type="button" class="btn btn--ghost" data-close-modal>Cancel</button>
            <button type="submit" class="btn btn--primary">Add workspace</button>
        </footer>
    </form>
</div>
@endsection
