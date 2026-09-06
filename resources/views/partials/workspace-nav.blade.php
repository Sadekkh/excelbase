@php
    $role = $role ?? null;
    $plan = $plan ?? $workspace->resolvedPlan();
    $surface = $surface ?? \App\Support\Access::surface(auth()->user(), $workspace);
    $canBuild = $canBuild ?? \App\Support\Access::canBuild(auth()->user(), $workspace);
@endphp
<nav class="saas-nav">
    <div class="saas-nav__brand">
        <a href="{{ route('workspaces.show', $workspace) }}">
            <span class="avatar avatar--sm">{{ strtoupper(substr($workspace->name, 0, 1)) }}</span>
            <strong>{{ $workspace->name }}</strong>
        </a>
        <span class="plan-pill">{{ $plan->name }}</span>
        @if ($role)<span class="role-pill">{{ \App\Support\Access::label($role) }}</span>@endif
    </div>
    <div class="saas-nav__links">
        @if ($surface === 'app')
            <a href="{{ route('workspaces.show', $workspace) }}" class="{{ request()->routeIs('workspaces.show') ? 'is-on' : '' }}">Home</a>
        @else
            <a href="{{ route('workspaces.show', $workspace) }}" class="{{ request()->routeIs('workspaces.show') ? 'is-on' : '' }}">Workspace</a>
        @endif
    </div>
    <div class="saas-nav__tools">
        @if ($canBuild)
            <form method="post" action="{{ route('surface.switch') }}" class="surface-switch">
                @csrf
                <input type="hidden" name="workspace_id" value="{{ $workspace->id }}">
                <input type="hidden" name="surface" value="{{ $surface === 'app' ? 'builder' : 'app' }}">
                <button type="submit" class="btn btn--ghost">{{ $surface === 'app' ? 'Open builder' : 'Open as user' }}</button>
            </form>
        @endif
        <a class="icon-btn" href="{{ route('notifications.index') }}" title="Inbox">@include('partials.icon', ['name' => 'info', 'size' => 16])</a>
        @if (auth()->user()->is_platform_admin)
            <a class="btn btn--ghost" href="{{ route('admin.index') }}">Admin</a>
        @endif
        <a class="btn btn--ghost" href="{{ route('dashboard') }}">Workspaces</a>
    </div>
</nav>
