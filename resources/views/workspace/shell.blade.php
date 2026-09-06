@extends('layouts.app')
@php
    $bodyClass = 'app-body';
@endphp
@section('title', 'Baserow')
@section('content')
<div class="app shell" id="app" data-surface="{{ $surface }}" style="--brand: {{ $workspace->brandColor() }}; --sidebar-bg: {{ $workspace->sidebarColor() }};">
    <div class="sidebar-backdrop" id="sidebar-backdrop" hidden></div>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar__head">
            <span class="sidebar__logo">
                <img class="sidebar__brand-logo" id="brand-logo" alt="" @if($workspace->logoUrl()) src="{{ $workspace->logoUrl() }}" @else hidden @endif>
                <span id="brand-mark" @if($workspace->logoUrl()) hidden @endif>@include('partials.logo', ['size' => 18])</span>
                <span id="brand-wordmark">Baserow</span>
            </span>
        </div>

        <button type="button" class="sidebar__workspace" data-menu="workspace-menu">
            <span class="avatar avatar--sm" id="workspace-avatar">
                <img id="workspace-avatar-img" alt="" @if($workspace->logoUrl()) src="{{ $workspace->logoUrl() }}" @else hidden @endif>
                <span id="workspace-avatar-letter" @if($workspace->logoUrl()) hidden @endif>{{ strtoupper(substr($workspace->name, 0, 1)) }}</span>
            </span>
            <span class="sidebar__workspace-name">{{ $workspace->name }}</span>
            @include('partials.icon', ['name' => 'chevron-down', 'size' => 14])
        </button>
        <div class="menu menu--sidebar" id="workspace-menu" hidden></div>

        <div class="mode-switch" id="mode-switch" hidden>
            <button type="button" class="mode-switch__btn" data-surface="app">Use</button>
            <button type="button" class="mode-switch__btn" data-surface="builder">Build</button>
        </div>
        <p class="mode-caption" id="mode-caption"></p>

        <div class="sidebar__scroll">
            <div class="sidebar__search">
                <span class="sidebar__search-box">
                    @include('partials.icon', ['name' => 'search', 'size' => 14])
                    <input type="search" id="sidebar-search" placeholder="Find a table" autocomplete="off">
                    <span class="kbd">/</span>
                </span>
            </div>
            <nav class="sidebar__nav" id="sidebar-nav"></nav>
        </div>

        <div class="sidebar__user">
            <div class="sidebar__user-info">
                <span class="avatar avatar--sm">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                <div>
                    <strong>{{ $user->name }}</strong>
                    <small id="user-role-label">{{ \App\Support\Access::label($role) }}</small>
                </div>
            </div>
            <button type="button" class="icon-btn" data-menu="user-menu" title="Account">
                @include('partials.icon', ['name' => 'more-v', 'size' => 16])
            </button>
            <div class="menu" id="user-menu" hidden></div>
        </div>
    </aside>

    <div class="workspace">
        <div class="sheet-top">
            <button type="button" class="icon-btn sidebar-toggle" id="sidebar-toggle" aria-label="Open menu">
                @include('partials.icon', ['name' => 'table', 'size' => 16])
            </button>
            <div class="sheet-chrome" id="sheet-chrome"></div>
        </div>
        <div class="panels">
            <div class="panel" id="panel-filters" hidden></div>
            <div class="panel" id="panel-sorts" hidden></div>
            <div class="panel" id="panel-groups" hidden></div>
            <div class="panel" id="panel-hidden" hidden></div>
        </div>
        <div class="view-stage" id="view-stage"></div>
    </div>
    <aside class="row-drawer" id="row-drawer" hidden></aside>
</div>
<script type="application/json" id="shell-boot">{!! json_encode($boot) !!}</script>
@endsection

@push('scripts')
<script src="{{ asset('js/table.js') }}"></script>
<script src="{{ asset('js/shell.js') }}"></script>
@endpush
