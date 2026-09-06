@extends('layouts.app')
@section('title', $workspace->name.' | Baserow')
@section('content')
<div class="home">
    @include('partials.topbar', ['pageTitle' => $workspace->name])
    <main class="home__main">
        <header class="home__head">
            <div>
                <a class="home__crumb" href="{{ route('dashboard') }}">All workspaces</a>
                <h1>{{ $workspace->name }}</h1>
                <p>Databases in this workspace. Open one to work with tables and views.</p>
            </div>
            <div class="home__actions">
                <button type="button" class="btn btn--ghost" data-open-modal="rename-workspace">Rename</button>
                <button type="button" class="btn btn--primary" data-open-modal="create-database">
                    @include('partials.icon', ['name' => 'plus', 'size' => 16])
                    Create database
                </button>
            </div>
        </header>

        @if ($workspace->databases->isEmpty())
            <div class="empty-state">
                @include('partials.icon', ['name' => 'database', 'size' => 28])
                <h2>No databases</h2>
                <p>A database holds tables. Create one to get a first table with a Name field and a grid view.</p>
                <button type="button" class="btn btn--primary" data-open-modal="create-database">Create database</button>
            </div>
        @else
            <div class="card-grid">
                @foreach ($workspace->databases as $database)
                    <a class="entity-card" href="{{ route('databases.show', $database) }}">
                        <div class="entity-card__icon entity-card__icon--database">
                            @include('partials.icon', ['name' => 'database', 'size' => 20])
                        </div>
                        <div class="entity-card__body">
                            <h3>{{ $database->name }}</h3>
                            <p>{{ $database->tables->count() }} {{ \Illuminate\Support\Str::plural('table', $database->tables->count()) }}</p>
                        </div>
                        @include('partials.icon', ['name' => 'chevron-right', 'size' => 16])
                    </a>
                @endforeach
            </div>
        @endif
    </main>
</div>

<div class="modal" id="create-database" hidden>
    <div class="modal__backdrop" data-close-modal></div>
    <form class="modal__dialog" method="post" action="{{ route('databases.store', $workspace) }}">
        @csrf
        <header class="modal__head">
            <h2>Create database</h2>
            <button type="button" class="icon-btn" data-close-modal>@include('partials.icon', ['name' => 'x'])</button>
        </header>
        <div class="modal__body">
            <label class="field">
                <span>Name</span>
                <input type="text" name="name" required placeholder="e.g. CRM">
            </label>
        </div>
        <footer class="modal__foot">
            <button type="button" class="btn btn--ghost" data-close-modal>Cancel</button>
            <button type="submit" class="btn btn--primary">Add database</button>
        </footer>
    </form>
</div>

<div class="modal" id="rename-workspace" hidden>
    <div class="modal__backdrop" data-close-modal></div>
    <form class="modal__dialog" method="post" action="{{ route('workspaces.update', $workspace) }}">
        @csrf
        @method('PATCH')
        <header class="modal__head">
            <h2>Rename workspace</h2>
            <button type="button" class="icon-btn" data-close-modal>@include('partials.icon', ['name' => 'x'])</button>
        </header>
        <div class="modal__body">
            <label class="field">
                <span>Name</span>
                <input type="text" name="name" required value="{{ $workspace->name }}">
            </label>
        </div>
        <footer class="modal__foot">
            <button type="button" class="btn btn--ghost" data-close-modal>Cancel</button>
            <button type="submit" class="btn btn--primary">Save</button>
        </footer>
    </form>
</div>
@endsection
