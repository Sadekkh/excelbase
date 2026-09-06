<nav class="saas-nav">
    <div class="saas-nav__brand">
        <a href="{{ route('admin.index') }}"><strong>Platform admin</strong></a>
    </div>
    <div class="saas-nav__links">
        <a href="{{ route('admin.index') }}" class="{{ request()->routeIs('admin.index') ? 'is-on' : '' }}">Overview</a>
        <a href="{{ route('admin.workspaces') }}" class="{{ request()->routeIs('admin.workspaces') ? 'is-on' : '' }}">Workspaces</a>
        <a href="{{ route('admin.users') }}" class="{{ request()->routeIs('admin.users') ? 'is-on' : '' }}">Users</a>
        <a href="{{ route('admin.plans') }}" class="{{ request()->routeIs('admin.plans') ? 'is-on' : '' }}">Plans</a>
    </div>
    <div class="saas-nav__tools">
        <a class="btn btn--ghost" href="{{ route('dashboard') }}">Exit admin</a>
    </div>
</nav>
