@extends('layouts.app')
@section('title', 'People · '.$workspace->name)
@section('content')
<div class="home home--saas">
    @include('partials.workspace-nav')
    <main class="home__main">
        @if (session('status'))<p class="banner">{{ session('status') }}</p>@endif
        @if ($errors->any())<p class="banner banner--error">{{ $errors->first() }}</p>@endif
        <header class="home__head">
            <div>
                <h1>People</h1>
                <p>Owners and admins manage the workspace. Builders create structure. Members use data. Viewers only read.</p>
            </div>
        </header>
        <div class="split">
            <table class="data-table">
                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th></th></tr></thead>
                <tbody>
                    @foreach ($workspace->members as $member)
                        <tr>
                            <td>{{ $member->name }}</td>
                            <td>{{ $member->email }}</td>
                            <td>
                                <form method="post" action="{{ route('members.update', [$workspace, $member]) }}" class="inline-form">
                                    @csrf
                                    @method('PATCH')
                                    <select name="role" onchange="this.form.submit()">
                                        @foreach (($plan->feature('roles') ? \App\Support\Access::ROLES : ['owner', 'member']) as $r)
                                            <option value="{{ $r }}" @selected($member->pivot->role === $r)>{{ \App\Support\Access::label($r) }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            </td>
                            <td>
                                @if ($member->id !== auth()->id() && $member->pivot->role !== 'owner')
                                    <form method="post" action="{{ route('members.destroy', [$workspace, $member]) }}" onsubmit="return confirm('Remove this person?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn--ghost" type="submit">Remove</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <form class="side-card" method="post" action="{{ route('members.store', $workspace) }}">
                @csrf
                <h2>Invite</h2>
                <label class="field"><span>Email</span><input type="email" name="email" required></label>
                <label class="field"><span>Name</span><input type="text" name="name" placeholder="Optional if they already have an account"></label>
                <label class="field"><span>Role</span>
                    <select name="role">
                        <option value="member">Member — uses tables and dashboards</option>
                        @if ($plan->feature('roles'))
                            <option value="builder">Builder — creates structure</option>
                            <option value="admin">Admin — people and plan</option>
                            <option value="viewer">Viewer — read only</option>
                        @endif
                    </select>
                </label>
                <button class="btn btn--primary" type="submit">Add person</button>
                <p class="hint">{{ $workspace->members->count() }} / {{ $plan->feature('members') }} seats on {{ $plan->name }}.</p>
            </form>
        </div>
    </main>
</div>
@endsection
