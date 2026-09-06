@extends('layouts.auth')
@section('title', 'Create account | Baserow')
@section('content')
<div class="auth">
    <div class="auth__panel">
        <a class="auth__brand" href="{{ route('login') }}">
            @include('partials.logo', ['size' => 28])
            <span>Baserow</span>
        </a>
        <h1 class="auth__title">Create a new account</h1>
        <p class="auth__lead">A workspace and first database are created for you.</p>

        @if ($errors->any())
            <div class="auth__error">{{ $errors->first() }}</div>
        @endif

        <form method="post" action="{{ route('register.store') }}" class="auth__form">
            @csrf
            <label class="field">
                <span>Name</span>
                <input type="text" name="name" value="{{ old('name') }}" required autofocus>
            </label>
            <label class="field">
                <span>Email address</span>
                <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
            </label>
            <label class="field">
                <span>Password</span>
                <input type="password" name="password" required autocomplete="new-password">
            </label>
            <label class="field">
                <span>Password confirmation</span>
                <input type="password" name="password_confirmation" required autocomplete="new-password">
            </label>
            <button type="submit" class="btn btn--primary btn--block">Create account</button>
        </form>

        <p class="auth__alt">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
    </div>
    <aside class="auth__aside">
        <div>
            <p class="auth__eyebrow">Get started</p>
            <h2>Your first table is one click away.</h2>
            <p class="auth__aside-copy">Create fields, collect data with a form, and switch to kanban when work needs a board — same product shape as Baserow.</p>
        </div>
    </aside>
</div>
@endsection
