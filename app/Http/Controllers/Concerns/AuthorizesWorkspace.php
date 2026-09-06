<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Database;
use App\Models\Field;
use App\Models\Row;
use App\Models\Table;
use App\Models\View;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;

trait AuthorizesWorkspace
{
    protected function workspaceForUser(int|string $id): Workspace
    {
        $workspace = auth()->user()->workspaces()->where('workspaces.id', $id)->first();
        if (! $workspace) {
            throw new AuthorizationException;
        }

        return $workspace;
    }

    protected function databaseForUser(Database $database): Database
    {
        $this->workspaceForUser($database->workspace_id);
        $database->loadMissing(['workspace', 'tables.fields', 'tables.views']);

        return $database;
    }

    protected function tableForUser(Table $table): Table
    {
        $table->loadMissing(['database.workspace', 'fields', 'views', 'rows']);
        $this->workspaceForUser($table->database->workspace_id);

        return $table;
    }

    protected function fieldForUser(Field $field): Field
    {
        $field->loadMissing('table.database');
        $this->tableForUser($field->table);

        return $field;
    }

    protected function rowForUser(Row $row): Row
    {
        $row->loadMissing('table.database');
        $this->tableForUser($row->table);

        return $row;
    }

    protected function viewForUser(View $view): View
    {
        $view->loadMissing('table.database');
        $this->tableForUser($view->table);

        return $view;
    }
}
