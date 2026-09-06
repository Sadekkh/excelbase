@extends('layouts.app')
@section('title', 'Plans · Admin')
@section('content')
<div class="home home--saas">
    @include('partials.admin-nav')
    <main class="home__main">
        @if (session('status'))<p class="banner">{{ session('status') }}</p>@endif
        <h1>Plans</h1>
        <div class="plan-grid">
            @foreach ($plans as $plan)
                <form class="plan-card" method="post" action="{{ route('admin.plans.update', $plan) }}">
                    @csrf
                    @method('PATCH')
                    <label class="field"><span>Name</span><input name="name" value="{{ $plan->name }}"></label>
                    <label class="field"><span>Price / user / month</span><input type="number" name="price_monthly" value="{{ $plan->price_monthly }}"></label>
                    <label class="field"><span>Tagline</span><input name="tagline" value="{{ $plan->tagline }}"></label>
                    <label class="field"><span>Seats</span><input type="number" name="members" value="{{ $plan->feature('members') }}"></label>
                    <label class="field"><span>Automations</span><input type="number" name="automations" value="{{ $plan->feature('automations') }}"></label>
                    <label class="field"><span>Dashboards</span><input type="number" name="dashboards" value="{{ $plan->feature('dashboards') }}"></label>
                    <button class="btn btn--primary" type="submit">Save {{ $plan->name }}</button>
                </form>
            @endforeach
        </div>
    </main>
</div>
@endsection
