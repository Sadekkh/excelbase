<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
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
        $search = $request->string('search')->toString() ?: null;
        $payload = \App\Support\TableBootstrap::make(
            $table,
            $user,
            $workspace,
            $request->integer('view') ?: null,
            $search,
        );
        $table = $payload['table'];
        $view = $payload['view'];
        $rows = $payload['rows'];
        $bootstrap = $payload['bootstrap'];

        if (! $request->expectsJson()) {
            return redirect()->route('workspaces.show', $workspace);
        }

        $table->database->workspace->load(['databases.tables']);
        $workspaces = auth()->user()->workspaces()->with('databases.tables')->get();

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
