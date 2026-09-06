@extends('layouts.auth')
@section('title', 'Sign in | Baserow')
@section('content')
<div class="auth">
    <div class="auth__panel">
        <a class="auth__brand" href="{{ route('login') }}">
            @include('partials.logo', ['size' => 28])
            <span>Baserow</span>
        </a>
        <h1 class="auth__title">Sign in</h1>
        <p class="auth__lead">Open-source no-code database. Your data, your workspace.</p>

        @if ($errors->any())
            <div class="auth__error">{{ $errors->first() }}</div>
        @endif

        <form method="post" action="{{ route('login.store') }}" class="auth__form">
            @csrf
            <label class="field">
                <span>Email address</span>
                <input type="email" name="email" value="{{ old('email', 'demo@baserow.io') }}" required autofocus autocomplete="email">
            </label>
            <label class="field">
                <span>Password</span>
                <input type="password" name="password" value="password" required autocomplete="current-password">
            </label>
            <label class="check">
                <input type="checkbox" name="remember" value="1" checked>
                <span>Remember me</span>
            </label>
            <button type="submit" class="btn btn--primary btn--block">Sign in</button>
        </form>

        <form method="post" action="{{ route('login.demo') }}" class="auth__demo">
            @csrf
            <button type="submit" class="btn btn--ghost btn--block">Continue with the demo workspace</button>
        </form>

        <p class="auth__alt">Don’t have an account? <a href="{{ route('register') }}">Create a new account</a></p>
        <p class="auth__hint">
            <strong>demo@baserow.io</strong> owner + platform admin ·
            <strong>sam@baserow.io</strong> builder ·
            <strong>maya@baserow.io</strong> member (user surface). Password for all: <strong>password</strong>
        </p>
    </div>
    <aside class="auth__aside">
        <div>
            <p class="auth__eyebrow">The data collaboration platform</p>
            <h2>Organize work the way Baserow does — grids, boards, galleries, and forms.</h2>
            <ul>
                <li>Spreadsheet-speed grid with typed fields</li>
                <li>Kanban, gallery, calendar, and public forms</li>
                <li>Workspaces, databases, and tables</li>
            </ul>
        </div>
    </aside>
</div>
@endsection
