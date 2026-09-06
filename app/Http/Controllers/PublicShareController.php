<?php

namespace App\Http\Controllers;

use App\Models\View;
use App\Support\FieldTypes;
use App\Support\RowQuery;
use App\Support\SelectColors;

class PublicShareController extends Controller
{
    public function show(string $slug)
    {
        $view = View::query()
            ->where('public_slug', $slug)
            ->where('public', true)
            ->with(['table.fields', 'table.rows', 'table.database.tables.fields', 'table.database.tables.rows'])
            ->firstOrFail();

        if ($view->type === 'form') {
            return redirect()->route('forms.public', $slug);
        }

        $table = $view->table;
        $table->rows->each(fn ($row) => $row->setRelation('table', $table));
        $rows = RowQuery::apply($table->rows, $view, $table->fields);

        $bootstrap = [
            'csrf' => csrf_token(),
            'readOnly' => true,
            'urls' => [
                'fields' => '#',
                'rows' => '#',
                'rowsBatch' => '#',
                'rowsDeleteMany' => '#',
                'views' => '#',
                'view' => '#',
                'viewDelete' => '#',
                'table' => url()->current(),
                'import' => '#',
                'export' => '#',
                'upload' => '#',
                'formPublic' => null,
                'shared' => url()->current(),
            ],
            'table' => ['id' => $table->id, 'name' => $table->name],
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
                'public' => true,
                'public_slug' => $view->public_slug,
                'is_personal' => false,
                'kanban_field_id' => $view->kanban_field_id,
            ],
            'me' => null,
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
                'read_only' => true,
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
            'linkedRows' => self::linkedRows($table),
            'linkedFields' => [],
            'routes' => [
                'field' => '#',
                'row' => '#',
                'database' => '#',
                'table' => '#',
                'view' => '#',
            ],
        ];

        return view('table.shared', [
            'table' => $table,
            'view' => $view,
            'bootstrap' => $bootstrap,
        ]);
    }

    public static function linkedRows($tableOrDatabase): array
    {
        $database = $tableOrDatabase instanceof \App\Models\Database
            ? $tableOrDatabase
            : $tableOrDatabase->database;
        $catalog = [];
        foreach ($database->tables as $related) {
            $primary = $related->fields->firstWhere('primary');
            $catalog[$related->id] = $related->rows->map(fn ($row) => [
                'id' => $row->id,
                'label' => $primary ? (RowQuery::display($row, $primary) ?: 'Untitled') : 'Row '.$row->id,
            ])->values();
        }

        return $catalog;
    }
}
