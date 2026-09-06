@extends('layouts.app')
@section('title', $dashboard->name.' · '.$workspace->name)
@section('content')
<div class="home home--saas">
    @include('partials.workspace-nav')
    <main class="home__main">
        @if (session('status'))<p class="banner">{{ session('status') }}</p>@endif
        <header class="home__head">
            <div>
                <a class="home__crumb" href="{{ route('dashboards.index', $workspace) }}">Dashboards</a>
                <h1>{{ $dashboard->name }}</h1>
                <p>{{ $dashboard->description }}</p>
            </div>
            @if ($canBuild)
                <form method="post" action="{{ route('dashboards.destroy', [$workspace, $dashboard]) }}" onsubmit="return confirm('Delete this dashboard?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn--ghost" type="submit">Delete dashboard</button>
                </form>
            @endif
        </header>
        @include('partials.widgets')
        @if ($canBuild)
            <form class="side-card" method="post" action="{{ route('dashboards.widgets.store', [$workspace, $dashboard]) }}" style="max-width:420px;margin-top:24px">
                @csrf
                <h2>Add widget</h2>
                <label class="field"><span>Title</span><input name="title" required placeholder="Open deals"></label>
                <label class="field"><span>Type</span>
                    <select name="type">
                        <option value="stat">Number</option>
                        <option value="chart">Chart</option>
                        <option value="list">Recent rows</option>
                    </select>
                </label>
                <label class="field"><span>Table</span>
                    <select name="table_id" required>
                        @foreach ($workspace->databases as $db)
                            @foreach ($db->tables as $tbl)
                                <option value="{{ $tbl->id }}">{{ $db->name }} / {{ $tbl->name }}</option>
                            @endforeach
                        @endforeach
                    </select>
                </label>
                <label class="field"><span>Field (sum / chart)</span>
                    <select name="field_id">
                        <option value=""></option>
                        @foreach ($workspace->databases as $db)
                            @foreach ($db->tables as $tbl)
                                @foreach ($tbl->fields as $f)
                                    <option value="{{ $f->id }}">{{ $tbl->name }} · {{ $f->name }}</option>
                                @endforeach
                            @endforeach
                        @endforeach
                    </select>
                </label>
                <label class="field"><span>Metric</span>
                    <select name="metric">
                        <option value="count">Count rows</option>
                        <option value="sum">Sum field</option>
                    </select>
                </label>
                <button class="btn btn--primary" type="submit">Add widget</button>
            </form>
        @endif
    </main>
</div>
@endsection
