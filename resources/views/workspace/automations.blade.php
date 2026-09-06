@extends('layouts.app')
@section('title', 'Automations · '.$workspace->name)
@section('content')
<div class="home home--saas">
    @include('partials.workspace-nav')
    <main class="home__main">
        @if (session('status'))<p class="banner">{{ session('status') }}</p>@endif
        @if ($errors->any())<p class="banner banner--error">{{ $errors->first() }}</p>@endif
        <header class="home__head">
            <div>
                <h1>Automations</h1>
                <p>Workflows run when rows are created or updated. {{ $workspace->automations->count() }} / {{ $plan->feature('automations') }} on {{ $plan->name }}.</p>
            </div>
        </header>
        <div class="split">
            <div>
                @forelse ($workspace->automations as $auto)
                    <article class="side-card" style="margin-bottom:12px">
                        <header class="home__head" style="margin:0">
                            <div>
                                <h3 style="margin:0">{{ $auto->name }}</h3>
                                <p>{{ $auto->table?->name }} · {{ str_replace('_', ' ', $auto->trigger) }} → {{ str_replace('_', ' ', $auto->action) }}</p>
                            </div>
                            <div class="home__actions">
                                <form method="post" action="{{ route('automations.update', [$workspace, $auto]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="enabled" value="{{ $auto->enabled ? 0 : 1 }}">
                                    <button class="btn btn--ghost" type="submit">{{ $auto->enabled ? 'Turn off' : 'Turn on' }}</button>
                                </form>
                                <form method="post" action="{{ route('automations.destroy', [$workspace, $auto]) }}" onsubmit="return confirm('Delete this automation?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn--ghost" type="submit">Delete</button>
                                </form>
                            </div>
                        </header>
                        @if ($auto->runs->first())
                            <p class="hint">Last run: {{ $auto->runs->first()->status }} — {{ $auto->runs->first()->message }}</p>
                        @endif
                    </article>
                @empty
                    <div class="empty-state"><h2>No automations</h2><p>Create a workflow to update fields, create rows, or notify the team.</p></div>
                @endforelse
            </div>
            <form class="side-card" method="post" action="{{ route('automations.store', $workspace) }}">
                @csrf
                <h2>New workflow</h2>
                <label class="field"><span>Name</span><input name="name" required placeholder="When a deal is won, notify builders"></label>
                <label class="field"><span>Table</span>
                    <select name="table_id" required>
                        @foreach ($workspace->databases as $db)
                            @foreach ($db->tables as $tbl)
                                <option value="{{ $tbl->id }}">{{ $db->name }} / {{ $tbl->name }}</option>
                            @endforeach
                        @endforeach
                    </select>
                </label>
                <label class="field"><span>When</span>
                    <select name="trigger">
                        <option value="row_created">A row is created</option>
                        <option value="row_updated">A row is updated</option>
                        <option value="field_changed">A field changes</option>
                    </select>
                </label>
                <label class="field"><span>Field that changed (optional)</span>
                    <select name="trigger_field_id">
                        <option value="">Any field</option>
                        @foreach ($workspace->databases as $db)
                            @foreach ($db->tables as $tbl)
                                @foreach ($tbl->fields as $f)
                                    <option value="{{ $f->id }}">{{ $tbl->name }} · {{ $f->name }}</option>
                                @endforeach
                            @endforeach
                        @endforeach
                    </select>
                </label>
                <label class="field"><span>Only if field equals</span>
                    <select name="if_field_id">
                        <option value="">No extra condition</option>
                        @foreach ($workspace->databases as $db)
                            @foreach ($db->tables as $tbl)
                                @foreach ($tbl->fields as $f)
                                    <option value="{{ $f->id }}">{{ $tbl->name }} · {{ $f->name }}</option>
                                @endforeach
                            @endforeach
                        @endforeach
                    </select>
                </label>
                <label class="field"><span>Equals value</span><input name="if_value" placeholder="Option id or text"></label>
                <label class="field"><span>Then</span>
                    <select name="action">
                        <option value="notify">Notify people</option>
                        <option value="update_field">Update a field on this row</option>
                        <option value="create_row">Create a row in another table</option>
                    </select>
                </label>
                <label class="field"><span>Update field</span>
                    <select name="action_field_id">
                        <option value=""></option>
                        @foreach ($workspace->databases as $db)
                            @foreach ($db->tables as $tbl)
                                @foreach ($tbl->fields as $f)
                                    <option value="{{ $f->id }}">{{ $tbl->name }} · {{ $f->name }}</option>
                                @endforeach
                            @endforeach
                        @endforeach
                    </select>
                </label>
                <label class="field"><span>Set / create value</span><input name="action_value" placeholder="Done or follow-up title"></label>
                <label class="field"><span>Create row in</span>
                    <select name="action_table_id">
                        <option value=""></option>
                        @foreach ($workspace->databases as $db)
                            @foreach ($db->tables as $tbl)
                                <option value="{{ $tbl->id }}">{{ $db->name }} / {{ $tbl->name }}</option>
                            @endforeach
                        @endforeach
                    </select>
                </label>
                <label class="field"><span>Notify</span>
                    <select name="notify_role">
                        <option value="builders">Owners, admins, builders</option>
                        <option value="everyone">Everyone in the workspace</option>
                        <option value="member">Members</option>
                    </select>
                </label>
                <label class="field"><span>Notification text</span><input name="notify_message" placeholder="A deal moved"></label>
                <button class="btn btn--primary" type="submit">Create automation</button>
            </form>
        </div>
    </main>
</div>
@endsection
