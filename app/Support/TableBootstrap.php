<?php

namespace App\Support;

use App\Http\Controllers\PublicShareController;
use App\Models\Table;
use App\Models\User;
use App\Models\View;
use App\Models\Workspace;
use Illuminate\Support\Collection;

class TableBootstrap
{
    /**
     * @return array{table: Table, view: View, rows: Collection, search: ?string, bootstrap: array<string, mixed>}
     */
    public static function make(Table $table, User $user, Workspace $workspace, ?int $viewId = null, ?string $search = null): array
    {
        $table->loadMissing(['fields', 'views', 'rows', 'database.tables.fields', 'database.tables.rows']);
        $canBuild = Access::canBuild($user, $workspace);
        $canEdit = Access::canEditData($user, $workspace);
        $surface = Access::surface($user, $workspace);

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

        $userId = (int) $user->id;
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

        $table->rows->each(fn ($row) => $row->setRelation('table', $table));
        $rows = RowQuery::apply($table->rows, $view, $table->fields, $search);

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
                'sheet' => route('workspaces.panel.sheet', [$workspace, $table]),
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
            'database' => [
                'id' => $table->database->id,
                'name' => $table->database->name,
            ],
            'views' => $visibleViews->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'type' => $item->type,
                'is_personal' => (bool) $item->is_personal,
            ])->values(),
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
                'id' => $user->id,
                'name' => $user->name,
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
            'search' => $search,
            'routes' => [
                'field' => url('/fields'),
                'row' => url('/rows'),
                'database' => url('/database'),
                'table' => url('/table'),
                'view' => url('/views'),
            ],
        ];

        return [
            'table' => $table,
            'view' => $view,
            'rows' => $rows,
            'search' => $search,
            'bootstrap' => $bootstrap,
        ];
    }
}
