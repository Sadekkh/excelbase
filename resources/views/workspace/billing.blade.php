@extends('layouts.app')
@section('title', 'Plan · '.$workspace->name)
@section('content')
<div class="home home--saas">
    @include('partials.workspace-nav')
    <main class="home__main">
        @if (session('status'))<p class="banner">{{ session('status') }}</p>@endif
        <header class="home__head">
            <div>
                <h1>Plan</h1>
                <p>{{ $workspace->name }} is on <strong>{{ $plan->name }}</strong>. This is a local SaaS billing page — choosing a plan updates limits immediately, no payment provider.</p>
            </div>
        </header>
        <div class="plan-grid">
            @foreach ($plans as $item)
                <article class="plan-card {{ $item->id === $plan->id ? 'is-current' : '' }}">
                    <h2>{{ $item->name }}</h2>
                    <p class="plan-card__price">${{ $item->price_monthly }}<small>/user/month</small></p>
                    <p>{{ $item->tagline }}</p>
                    <ul>
                        <li>{{ $item->feature('members') }} seats</li>
                        <li>{{ $item->feature('automations') }} automations</li>
                        <li>{{ $item->feature('dashboards') }} dashboards</li>
                        <li>{{ implode(', ', $item->feature('views', [])) }} views</li>
                    </ul>
                    @if ($item->id === $plan->id)
                        <span class="btn btn--ghost" aria-disabled="true">Current plan</span>
                    @else
                        <form method="post" action="{{ route('workspaces.plan', $workspace) }}">
                            @csrf
                            <input type="hidden" name="plan_id" value="{{ $item->id }}">
                            <button class="btn btn--primary" type="submit">Switch to {{ $item->name }}</button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>
    </main>
</div>
@endsection
