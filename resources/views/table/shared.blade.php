@extends('layouts.app')
@php($bodyClass = 'app-body')
@section('title', $view->name.' · '.$table->name.' | Baserow')
@section('content')
<div class="app shared-app" id="app">
    <section class="workspace">
        <div class="views-bar">
            <a class="sidebar__logo" href="{{ url('/') }}" style="padding-left:12px">
                @include('partials.logo', ['size' => 18])
                <span>Baserow</span>
            </a>
            <div class="views-bar__meta">
                <strong>{{ $table->name }}</strong>
                <span class="dot">·</span>
                <span>{{ $view->name }}</span>
                <span class="dot">·</span>
                <span>Shared view</span>
            </div>
        </div>
        <div class="view-stage view-stage--{{ $view->type }} view-stage--{{ $view->row_height }}" id="view-stage"></div>
    </section>
</div>
<script type="application/json" id="table-bootstrap">{!! json_encode($bootstrap) !!}</script>
@endsection
@push('scripts')
<script src="{{ asset('js/table.js') }}"></script>
@endpush
