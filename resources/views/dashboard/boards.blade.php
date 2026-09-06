@extends('layouts.app')
@section('title', 'Dashboards · '.$workspace->name)
@section('content')
<div class="home home--saas">
    @include('partials.workspace-nav')
    <main class="home__main">
        @if (session('status'))<p class="banner">{{ session('status') }}</p>@endif
        @if ($errors->any())<p class="banner banner--error">{{ $errors->first() }}</p>@endif
        <header class="home__head">
            <div>
                <h1>Dashboards</h1>
                <p>Published boards for people using the workspace. {{ $workspace->dashboards->count() }} / {{ $plan->feature('dashboards') }} on {{ $plan->name }}.</p>
            </div>
        </header>
        <div class="card-grid">
            @foreach ($workspace->dashboards as $board)
                <a class="entity-card" href="{{ route('dashboards.show', [$workspace, $board]) }}">
                    <div class="entity-card__icon">@include('partials.icon', ['name' => 'grid', 'size' => 20])</div>
                    <div class="entity-card__body">
                        <h3>{{ $board->name }}</h3>
                        <p>{{ $board->description ?: $board->widgets->count().' widgets' }}</p>
                    </div>
                </a>
            @endforeach
        </div>
        @if ($canBuild)
            <form class="side-card" method="post" action="{{ route('dashboards.store', $workspace) }}" style="max-width:420px;margin-top:24px">
                @csrf
                <h2>New dashboard</h2>
                <label class="field"><span>Name</span><input name="name" required placeholder="Sales overview"></label>
                <label class="field"><span>Description</span><input name="description" placeholder="What this board is for"></label>
                <button class="btn btn--primary" type="submit">Create dashboard</button>
            </form>
        @endif
    </main>
</div>
@endsection
