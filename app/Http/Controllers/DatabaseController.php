<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Models\Database;
use App\Models\Workspace;
use Illuminate\Http\Request;

class DatabaseController extends Controller
{
    use AuthorizesWorkspace;

    public function store(Request $request, Workspace $workspace)
    {
        $workspace = $this->workspaceForUser($workspace->id);
        $this->assertCanBuild($workspace);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $database = Database::createWithTable($workspace, $data['name']);
        $table = $database->tables()->first();

        return $request->expectsJson()
            ? response()->json(['database' => $database, 'table_id' => $table?->id])
            : redirect()->route('tables.show', $table);
    }

    public function show(Database $database)
    {
        $this->databaseForUser($database);
        $table = $database->tables()->first();

        if (! $table) {
            return redirect()->route('workspaces.show', $database->workspace_id);
        }

        return redirect()->route('tables.show', $table);
    }

    public function update(Request $request, Database $database)
    {
        $this->databaseForUser($database);
        $this->assertCanBuild($this->workspaceForUser($database->workspace_id));
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);
        $database->update($data);

        return $request->expectsJson()
            ? response()->json($database)
            : back();
    }

    public function destroy(Request $request, Database $database)
    {
        $workspaceId = $database->workspace_id;
        $this->databaseForUser($database);
        $this->assertCanBuild($this->workspaceForUser($database->workspace_id));
        $database->delete();

        return $request->expectsJson()
            ? response()->json(['ok' => true])
            : redirect()->route('workspaces.show', $workspaceId);
    }
}
