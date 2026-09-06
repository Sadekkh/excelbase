<header class="topbar">
    <a class="topbar__brand" href="{{ route('dashboard') }}">
        @include('partials.logo', ['size' => 20])
        <span>Baserow</span>
    </a>
    <div class="topbar__title">{{ $pageTitle ?? '' }}</div>
    <div class="topbar__user" data-menu="user-menu">
        <span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
        <span class="topbar__name">{{ auth()->user()->name }}</span>
        @include('partials.icon', ['name' => 'chevron-down', 'size' => 14])
        <div class="menu" id="user-menu" hidden>
            <div class="menu__meta">{{ auth()->user()->email }}</div>
            <a href="{{ route('app') }}">Profile</a>
            <a href="{{ route('dashboard') }}">Workspaces</a>
            <a href="{{ route('notifications.index') }}">Inbox</a>
            @if (auth()->user()->is_platform_admin)
                <a href="{{ route('admin.index') }}">Platform admin</a>
            @endif
            <form method="post" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Log out</button>
            </form>
        </div>
    </div>
</header>
