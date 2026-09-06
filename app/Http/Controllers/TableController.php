<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Http\Controllers\PublicShareController;
use App\Models\Database;
use App\Models\Table;
use App\Support\Access;
use App\Support\FieldTypes;
use App\Support\RowQuery;
use App\Support\SelectColors;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TableController extends Controller
{
    use AuthorizesWorkspace;

    public function store(Request $request, Database $database)
    {
        $this->databaseForUser($database);
        $this->assertCanBuild($this->workspaceForUser($database->workspace_id));
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $table = Table::createWithDefaults($database, $data['name']);

        return $request->expectsJson()
            ? response()->json($table)
            : redirect()->route('tables.show', $table);
    }

    public function show(Request $request, Table $table)
    {
        $table = $this->tableForUser($table);
        $workspace = $this->workspaceOfTable($table);
        $user = $request->user();
        $canBuild = Access::canBuild($user, $workspace);
        $canEdit = Access::canEditData($user, $workspace);
        $surface = Access::surface($user, $workspace);
        $viewId = $request->integer('view');
        $view = $viewId
            ? $table->views->firstWhere('id', $viewId)
            : $table->views->first();

        if (! $view) {
            $view = $table->views()->create([
                'name' => 'Grid',
                'type' => 'grid',
                'filters' => [],
                'sorts' => [],
                'groups' => [],
                'hidden_fields' => [],
                'row_height' => 'small',
                'order' => 1,
            ]);
            $table->load('views');
        }

        $userId = (int) $request->user()->id;
        $visibleViews = $table->views
            ->filter(fn ($item) => ! $item->is_personal || (int) $item->user_id === $userId)
            ->values();
        $table->setRelation('views', $visibleViews);

        if (! $view || ($view->is_personal && (int) $view->user_id !== $userId) || ! $visibleViews->contains('id', $view->id)) {
            $view = $visibleViews->first();
        }

        if (! $view) {
            $view = $table->views()->create([
                'name' => 'Grid',
                'type' => 'grid',
                'filters' => [],
                'sorts' => [],
                'groups' => [],
                'hidden_fields' => [],
                'row_height' => 'small',
                'order' => 1,
                'user_id' => $userId,
            ]);
            $table->setRelation('views', collect([$view]));
        }

        $search = $request->string('search')->toString() ?: null;
        $table->rows->each(fn ($row) => $row->setRelation('table', $table));
        $rows = RowQuery::apply($table->rows, $view, $table->fields, $search);

        $table->database->workspace->load(['databases.tables']);
        $workspaces = auth()->user()->workspaces()->with('databases.tables')->get();

        $bootstrap = [
            'csrf' => csrf_token(),
            'urls' => [
                'fields' => route('fields.store', $table),
                'rows' => route('rows.store', $table),
                'rowsBatch' => route('rows.batch', $table),
                'rowsDeleteMany' => route('rows.destroy-many', $table),
                'views' => route('views.store', $table),
                'view' => route('views.update', $view),
                'viewDelete' => route('views.destroy', $view),
                'table' => route('tables.show', $table),
                'import' => route('tables.import', $table),
                'export' => route('tables.export', $table),
                'formPublic' => $view->public_slug && $view->type === 'form' ? route('forms.public', $view->public_slug) : null,
                'shared' => $view->public_slug ? route('views.shared', $view->public_slug) : null,
                'upload' => route('files.store'),
                'comments' => url('/rows'),
                'exportJson' => route('tables.export', [$table, 'format' => 'json']),
                'exportXml' => route('tables.export', [$table, 'format' => 'xml']),
                'exportXls' => route('tables.export', [$table, 'format' => 'xls']),
            ],
            'table' => [
                'id' => $table->id,
                'name' => $table->name,
            ],
            'view' => [
                'id' => $view->id,
                'name' => $view->name,
                'type' => $view->type,
                'filters' => $view->filters ?? [],
                'sorts' => $view->sorts ?? [],
                'groups' => $view->groups ?? [],
                'hidden_fields' => $view->hidden_fields ?? [],
                'field_options' => $view->field_options ?? [],
                'form_config' => $view->form_config ?? [],
                'row_height' => $view->row_height,
                'public' => $view->public,
                'public_slug' => $view->public_slug,
                'is_personal' => $view->is_personal,
                'user_id' => $view->user_id,
                'kanban_field_id' => $view->kanban_field_id,
            ],
            'me' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
            ],
            'fields' => $table->fields->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->name,
                'type' => $f->type,
                'primary' => $f->primary,
                'options' => $f->options,
                'width' => $f->width,
                'order' => $f->order,
                'label' => $f->label(),
                'icon' => $f->icon(),
                'read_only' => $f->isReadOnly(),
                'operators' => FieldTypes::operators($f->type),
                'summaries' => FieldTypes::summaries($f->type),
            ]),
            'rows' => $rows->map(fn ($r) => $r->toApi($table->fields))->values(),
            'fieldTypes' => FieldTypes::all(),
            'selectColors' => SelectColors::all(),
            'siblingTables' => $table->database->tables->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
            ])->values(),
            'linkedRows' => PublicShareController::linkedRows($table->database->loadMissing(['tables.fields', 'tables.rows'])),
            'linkedFields' => $table->database->tables->mapWithKeys(fn ($sibling) => [
                $sibling->id => $sibling->fields->map(fn ($field) => [
                    'id' => $field->id,
                    'name' => $field->name,
                    'type' => $field->type,
                ])->values(),
            ]),
            'readOnly' => ! $canEdit,
            'canBuild' => $canBuild && $surface === 'builder',
            'role' => Access::role($user, $workspace),
            'surface' => $surface,
            'routes' => [
                'field' => url('/fields'),
                'row' => url('/rows'),
                'database' => url('/database'),
                'table' => url('/table'),
                'view' => url('/views'),
            ],
        ];

        return view('table.show', [
            'table' => $table,
            'database' => $table->database,
            'workspace' => $table->database->workspace,
            'workspaces' => $workspaces,
            'view' => $view,
            'rows' => $rows,
            'search' => $search,
            'fieldTypes' => FieldTypes::all(),
            'selectColors' => SelectColors::all(),
            'user' => $user,
            'bootstrap' => $bootstrap,
            'canBuild' => $canBuild,
            'showBuilderTools' => $canBuild && $surface === 'builder',
            'canEdit' => $canEdit,
            'role' => Access::role($user, $workspace),
            'plan' => $workspace->resolvedPlan(),
            'surface' => $surface,
        ]);
    }

    public function update(Request $request, Table $table)
    {
        $this->tableForUser($table);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);
        $table->update($data);

        return $request->expectsJson()
            ? response()->json($table)
            : back();
    }

    public function destroy(Request $request, Table $table)
    {
        $this->tableForUser($table);
        $database = $table->database;
        $table->delete();
        $next = $database->tables()->first();

        return $request->expectsJson()
            ? response()->json(['redirect' => $next ? route('tables.show', $next) : route('workspaces.show', $database->workspace_id)])
            : redirect($next ? route('tables.show', $next) : route('workspaces.show', $database->workspace_id));
    }

    public function duplicate(Request $request, Table $table)
    {
        $this->tableForUser($table);
        $copy = $table->duplicate();

        return $request->expectsJson()
            ? response()->json($copy)
            : redirect()->route('tables.show', $copy);
    }

    public function export(Request $request, Table $table)
    {
        $this->tableForUser($table);
        $fields = $table->fields;
        $table->rows->each(fn ($row) => $row->setRelation('table', $table));
        $rows = $table->rows()->orderBy('order')->get();
        $rows->each(fn ($row) => $row->setRelation('table', $table));
        $slug = \Illuminate\Support\Str::slug($table->name);
        $format = $request->query('format', 'csv');

        $matrix = $rows->map(function ($row) use ($fields) {
            $line = [];
            foreach ($fields as $field) {
                $line[$field->name] = RowQuery::display($row, $field);
            }

            return $line;
        })->values();

        if ($format === 'json') {
            return response()->json($matrix)->header('Content-Disposition', "attachment; filename=\"{$slug}.json\"");
        }

        if ($format === 'xml') {
            $xml = new \SimpleXMLElement('<table/>');
            $xml->addAttribute('name', $table->name);
            foreach ($matrix as $line) {
                $item = $xml->addChild('row');
                foreach ($line as $key => $value) {
                    $item->addChild(\Illuminate\Support\Str::slug($key, '_'), htmlspecialchars((string) $value));
                }
            }

            return response($xml->asXML(), 200, [
                'Content-Type' => 'application/xml',
                'Content-Disposition' => "attachment; filename=\"{$slug}.xml\"",
            ]);
        }

        $delimiter = $format === 'xls' ? "\t" : ',';
        $ext = $format === 'xls' ? 'xls' : 'csv';
        $mime = $format === 'xls' ? 'application/vnd.ms-excel' : 'text/csv';

        return response()->streamDownload(function () use ($fields, $matrix, $delimiter) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $fields->pluck('name')->all(), $delimiter);
            foreach ($matrix as $line) {
                fputcsv($out, array_values($line), $delimiter);
            }
            fclose($out);
        }, "{$slug}.{$ext}", ['Content-Type' => $mime]);
    }

    public function import(Request $request, Table $table)
    {
        $this->tableForUser($table);
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $headers = fgetcsv($handle) ?: [];
        $fields = $table->fields;
        $map = [];
        foreach ($headers as $i => $header) {
            $field = $fields->first(fn ($f) => strcasecmp($f->name, trim((string) $header)) === 0);
            $map[$i] = $field;
        }

        $order = (float) ($table->rows()->max('order') ?? 0);
        $created = 0;

        DB::transaction(function () use ($handle, $map, $table, &$order, &$created) {
            while (($line = fgetcsv($handle)) !== false) {
                $data = [];
                foreach ($line as $i => $cell) {
                    $field = $map[$i] ?? null;
                    if (! $field || $field->isReadOnly()) {
                        continue;
                    }
                    if ($field->isSelect()) {
                        $option = collect($field->options['options'] ?? [])->first(
                            fn ($o) => strcasecmp($o['value'], trim((string) $cell)) === 0
                        );
                        if (! $option && trim((string) $cell) !== '') {
                            $option = $field->addOption(trim((string) $cell));
                        }
                        $data[(string) $field->id] = $field->type === 'multiple_select'
                            ? ($option ? [$option['id']] : [])
                            : ($option['id'] ?? null);
                    } else {
                        $data[(string) $field->id] = $cell === '' ? null : $cell;
                    }
                }
                $order += 1;
                $table->rows()->create(['data' => $data, 'order' => $order]);
                $created++;
            }
        });
        fclose($handle);

        return $request->expectsJson()
            ? response()->json(['created' => $created])
            : back()->with('status', "Imported {$created} rows.");
    }
}
