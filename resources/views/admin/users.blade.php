@extends('layouts.app')
@section('title', 'Users · Admin')
@section('content')
<div class="home home--saas">
    @include('partials.admin-nav')
    <main class="home__main">
        @if (session('status'))<p class="banner">{{ session('status') }}</p>@endif
        <h1>Users</h1>
        <table class="data-table">
            <thead><tr><th>Name</th><th>Email</th><th>Workspaces</th><th>Platform admin</th></tr></thead>
            <tbody>
                @foreach ($users as $u)
                    <tr>
                        <td>{{ $u->name }}</td>
                        <td>{{ $u->email }}</td>
                        <td>{{ $u->workspaces->pluck('name')->join(', ') ?: '—' }}</td>
                        <td>
                            @if ($u->id === auth()->id())
                                You
                            @else
                                <form method="post" action="{{ route('admin.users.toggle', $u) }}">
                                    @csrf
                                    <button class="btn btn--ghost" type="submit">{{ $u->is_platform_admin ? 'Revoke' : 'Grant' }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </main>
</div>
@endsection
