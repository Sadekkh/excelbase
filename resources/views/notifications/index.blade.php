@extends('layouts.app')
@section('title', 'Inbox | Baserow')
@section('content')
<div class="home">
    @include('partials.topbar', ['pageTitle' => 'Inbox'])
    <main class="home__main">
        <h1>Inbox</h1>
        @forelse ($notifications as $item)
            <form method="post" action="{{ route('notifications.read', $item) }}" class="side-card" style="margin-bottom:10px">
                @csrf
                <button type="submit" style="width:100%;text-align:left;background:none">
                    <strong>{{ $item->title }}</strong>
                    <p>{{ $item->body }}</p>
                    <p class="hint">{{ $item->read_at ? 'Read' : 'Unread' }} · {{ $item->created_at->diffForHumans() }}</p>
                </button>
            </form>
        @empty
            <div class="empty-state"><h2>No notifications</h2><p>Automations notify people here when a workflow runs.</p></div>
        @endforelse
    </main>
</div>
@endsection
