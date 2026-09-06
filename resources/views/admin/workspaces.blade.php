@extends('layouts.app')
@section('title', 'Workspaces · Admin')
@section('content')
<div class="home home--saas">
    @include('partials.admin-nav')
    <main class="home__main">
        @if (session('status'))<p class="banner">{{ session('status') }}</p>@endif
        <h1>Client workspaces</h1>
        <table class="data-table">
            <thead><tr><th>Workspace</th><th>People</th><th>Plan</th><th></th></tr></thead>
            <tbody>
                @foreach ($workspaces as $ws)
                    <tr>
                        <td><a href="{{ route('workspaces.show', $ws) }}">{{ $ws->name }}</a><div class="hint">{{ $ws->slug }}</div></td>
                        <td>{{ $ws->members->count() }}</td>
                        <td>
                            <form method="post" action="{{ route('admin.workspaces.plan', $ws) }}" class="inline-form">
                                @csrf
                                <select name="plan_id" onchange="this.form.submit()">
                                    @foreach ($plans as $plan)
                                        <option value="{{ $plan->id }}" @selected($ws->plan_id === $plan->id)>{{ $plan->name }}</option>
                                    @endforeach
                                </select>
                            </form>
                        </td>
                        <td><a class="btn btn--ghost" href="{{ route('workspaces.show', $ws) }}">Open workspace</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </main>
</div>
@endsection
