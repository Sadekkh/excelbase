@extends('layouts.app')
@php($bodyClass = 'app-body')
@section('title', $table->name.' | '.$database->name.' | Baserow')
@section('content')
<div class="app" id="app"
     data-table-id="{{ $table->id }}"
     data-view-id="{{ $view->id }}"
     data-view-type="{{ $view->type }}">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar__head">
            <a class="sidebar__logo" href="{{ route('dashboard') }}">
                @include('partials.logo', ['size' => 18])
                <span>Baserow</span>
            </a>
        </div>

        <button type="button" class="sidebar__workspace" data-menu="workspace-menu">
            <span class="avatar avatar--sm">{{ strtoupper(substr($workspace->name, 0, 1)) }}</span>
            <span class="sidebar__workspace-name">{{ $workspace->name }}</span>
            @include('partials.icon', ['name' => 'chevron-down', 'size' => 14])
        </button>
        <div class="menu menu--sidebar" id="workspace-menu" hidden>
            @foreach ($workspaces as $ws)
                <a href="{{ route('workspaces.show', $ws) }}" class="{{ $ws->id === $workspace->id ? 'is-active' : '' }}">{{ $ws->name }}</a>
            @endforeach
            <div class="menu__sep"></div>
            <a href="{{ route('dashboard') }}">All workspaces</a>
        </div>

        <div class="sidebar__scroll">
            <div class="sidebar__search">
                <span class="sidebar__search-box">
                    @include('partials.icon', ['name' => 'search', 'size' => 14])
                    <input type="search" id="sidebar-search" placeholder="Search" autocomplete="off">
                    <span class="kbd">/</span>
                </span>
            </div>

            @foreach ($workspace->databases as $db)
                <div class="tree-db-wrap" data-search-text="{{ strtolower($db->name) }}">
                <details class="tree-db" {{ $db->id === $database->id ? 'open' : '' }}>
                    <summary>
                        <span class="tree-db__icon">@include('partials.icon', ['name' => 'database', 'size' => 14])</span>
                        <span class="tree-db__name">{{ $db->name }}</span>
                    </summary>
                    <ul class="tree-tables">
                        @foreach ($db->tables as $tbl)
                            <li data-search-text="{{ strtolower($tbl->name.' '.$db->name) }}">
                                <a href="{{ route('tables.show', $tbl) }}" class="tree-table {{ $tbl->id === $table->id ? 'is-active' : '' }}">
                                    @include('partials.icon', ['name' => 'table', 'size' => 14])
                                    <span>{{ $tbl->name }}</span>
                                </a>
                                <button type="button" class="icon-btn icon-btn--tiny" data-menu="tbl-menu-{{ $tbl->id }}">
                                    @include('partials.icon', ['name' => 'more', 'size' => 14])
                                </button>
                                <div class="menu" id="tbl-menu-{{ $tbl->id }}" hidden>
                                    <button type="button" data-rename="table" data-id="{{ $tbl->id }}" data-name="{{ $tbl->name }}">Rename</button>
                                    <form method="post" action="{{ route('tables.duplicate', $tbl) }}">@csrf<button type="submit">Duplicate</button></form>
                                    <a href="{{ route('tables.export', $tbl) }}">Export CSV</a>
                                    <button type="button" class="is-danger" data-delete="table" data-id="{{ $tbl->id }}">Delete</button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </details>
                <button type="button" class="icon-btn icon-btn--tiny tree-db__more" data-menu="db-menu-{{ $db->id }}">
                    @include('partials.icon', ['name' => 'more', 'size' => 14])
                </button>
                <div class="menu" id="db-menu-{{ $db->id }}" hidden>
                    <button type="button" data-rename="database" data-id="{{ $db->id }}" data-name="{{ $db->name }}">Rename</button>
                    <button type="button" data-create-table="{{ $db->id }}">Create table</button>
                    <button type="button" class="is-danger" data-delete="database" data-id="{{ $db->id }}">Delete</button>
                </div>
                </div>
            @endforeach
        </div>

        <div class="sidebar__user">
            <div class="sidebar__user-info">
                <span class="avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                <div>
                    <strong>{{ $user->name }}</strong>
                    <small>{{ $user->email }}</small>
                </div>
            </div>
            <form method="post" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="icon-btn" title="Log out">@include('partials.icon', ['name' => 'logout'])</button>
            </form>
        </div>
    </aside>

    <div class="sidebar-backdrop" id="sidebar-backdrop" hidden></div>
    <section class="workspace">
        <div class="views-bar">
            <button type="button" class="icon-btn sidebar-toggle" id="sidebar-toggle" aria-label="Open sidebar">
                @include('partials.icon', ['name' => 'table', 'size' => 16])
            </button>
            <div class="views-bar__tabs">
                @foreach ($table->views as $v)
                    <a href="{{ route('tables.show', ['table' => $table, 'view' => $v->id]) }}"
                       class="view-tab {{ $v->id === $view->id ? 'is-active' : '' }}">
                        @include('partials.icon', ['name' => $v->type === 'grid' ? 'grid' : $v->type, 'size' => 14])
                        <span>{{ $v->name }}</span>
                    </a>
                @endforeach
                <button type="button" class="view-tab view-tab--add" data-menu="add-view">
                    @include('partials.icon', ['name' => 'plus', 'size' => 14])
                </button>
                <div class="menu" id="add-view" hidden>
                    @foreach (['grid' => 'Grid', 'gallery' => 'Gallery', 'kanban' => 'Kanban', 'calendar' => 'Calendar', 'form' => 'Form'] as $type => $label)
                        <button type="button" data-create-view="{{ $type }}">
                            @include('partials.icon', ['name' => $type, 'size' => 14])
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>
            <div class="views-bar__meta">
                <span>{{ $database->name }}</span>
                <span class="dot">·</span>
                <strong>{{ $table->name }}</strong>
            </div>
        </div>

        @if ($view->type !== 'form')
        <div class="toolbar">
            <button type="button" class="tool {{ count($view->filters ?? []) ? 'is-on' : '' }}" data-panel="filters">
                @include('partials.icon', ['name' => 'filter']) Filter
                @if (count($view->filters ?? [])) <em>{{ count($view->filters) }}</em> @endif
            </button>
            <button type="button" class="tool {{ count($view->sorts ?? []) ? 'is-on' : '' }}" data-panel="sorts">
                @include('partials.icon', ['name' => 'sort']) Sort
                @if (count($view->sorts ?? [])) <em>{{ count($view->sorts) }}</em> @endif
            </button>
            @if ($view->type === 'grid')
            <button type="button" class="tool {{ count($view->groups ?? []) ? 'is-on' : '' }}" data-panel="groups">
                @include('partials.icon', ['name' => 'group']) Group
            </button>
            @endif
            <button type="button" class="tool" data-panel="hidden">
                @include('partials.icon', ['name' => 'hide']) Hide fields
                @if (count($view->hidden_fields ?? [])) <em>{{ count($view->hidden_fields) }}</em> @endif
            </button>
            @if ($view->type === 'grid')
            <button type="button" class="tool" data-menu="row-height">
                @include('partials.icon', ['name' => 'row-height']) Row height
            </button>
            <div class="menu" id="row-height" hidden>
                @foreach (['small' => 'Short', 'medium' => 'Medium', 'large' => 'Tall'] as $key => $label)
                    <button type="button" data-row-height="{{ $key }}" class="{{ $view->row_height === $key ? 'is-active' : '' }}">{{ $label }}</button>
                @endforeach
            </div>
            @endif
            <button type="button" class="tool" data-menu="color-menu">
                @include('partials.icon', ['name' => 'color']) Color
            </button>
            <div class="menu" id="color-menu" hidden>
                <button type="button" data-row-color="">No coloring</button>
                @foreach ($table->fields->where('type', 'single_select') as $colorField)
                    <button type="button" data-row-color="{{ $colorField->id }}">{{ $colorField->name }}</button>
                @endforeach
            </div>
            <button type="button" class="tool" data-menu="share-menu">
                @include('partials.icon', ['name' => 'share']) Share
            </button>
            <div class="menu" id="share-menu" hidden>
                <button type="button" data-share-view>Create public link</button>
                <a href="{{ route('tables.export', $table) }}">Export view CSV</a>
            </div>
            <form class="toolbar__search" method="get" action="{{ route('tables.show', $table) }}">
                <input type="hidden" name="view" value="{{ $view->id }}">
                @include('partials.icon', ['name' => 'search-sm', 'size' => 14])
                <input type="search" name="search" value="{{ $search }}" placeholder="Search" id="grid-search">
            </form>
            <button type="button" class="icon-btn" data-menu="view-menu">@include('partials.icon', ['name' => 'more-v'])</button>
            <div class="menu" id="view-menu" hidden>
                <button type="button" data-rename="view" data-id="{{ $view->id }}" data-name="{{ $view->name }}">Rename view</button>
                <form method="post" action="{{ route('views.duplicate', $view) }}">@csrf<button type="submit">Duplicate view</button></form>
                <label class="menu__file">Import CSV
                    <input type="file" accept=".csv,text/csv" id="import-csv">
                </label>
                @if ($table->views->count() > 1)
                    <button type="button" class="is-danger" data-delete="view" data-id="{{ $view->id }}">Delete view</button>
                @endif
            </div>
        </div>
        @endif

        <div class="panels">
            <div class="panel" id="panel-filters" hidden></div>
            <div class="panel" id="panel-sorts" hidden></div>
            <div class="panel" id="panel-groups" hidden></div>
            <div class="panel" id="panel-hidden" hidden></div>
        </div>

        <div class="view-stage view-stage--{{ $view->type }} view-stage--{{ $view->row_height }}" id="view-stage"></div>
    </section>

    <aside class="row-drawer" id="row-drawer" hidden></aside>
</div>

<script type="application/json" id="table-bootstrap">{!! json_encode($bootstrap) !!}</script>
@endsection

@push('scripts')
<script src="{{ asset('js/table.js') }}"></script>
@endpush
