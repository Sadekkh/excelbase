<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Models\Database;
use App\Models\Table;
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

        $search = $request->string('search')->toString() ?: null;
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
                'formPublic' => $view->public_slug ? route('forms.public', $view->public_slug) : null,
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
                'kanban_field_id' => $view->kanban_field_id,
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
            'user' => auth()->user(),
            'bootstrap' => $bootstrap,
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

    public function export(Table $table): StreamedResponse
    {
        $this->tableForUser($table);
        $fields = $table->fields;
        $filename = \Illuminate\Support\Str::slug($table->name).'.csv';

        return response()->streamDownload(function () use ($table, $fields) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $fields->pluck('name')->all());
            foreach ($table->rows()->orderBy('order')->get() as $row) {
                $line = [];
                foreach ($fields as $field) {
                    $line[] = RowQuery::display($row, $field);
                }
                fputcsv($out, $line);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
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
