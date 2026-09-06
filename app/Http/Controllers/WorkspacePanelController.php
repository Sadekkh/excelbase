<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Models\Dashboard;
use App\Models\Table;
use App\Models\Workspace;
use App\Support\Access;
use App\Support\TableBootstrap;
use App\Support\WorkspaceShell;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspacePanelController extends Controller
{
    use AuthorizesWorkspace;

    public function boot(Workspace $workspace): JsonResponse
    {
        $workspace = $this->workspaceForUser($workspace->id);

        return response()->json(WorkspaceShell::boot(auth()->user(), $workspace));
    }

    public function sheet(Request $request, Workspace $workspace, Table $table): JsonResponse
    {
        $workspace = $this->workspaceForUser($workspace->id);
        $table = $this->tableForUser($table);
        abort_unless((int) $table->database->workspace_id === (int) $workspace->id, 404);

        $payload = TableBootstrap::make(
            $table,
            $request->user(),
            $workspace,
            $request->integer('view') ?: null,
            $request->string('search')->toString() ?: null,
        );

        return response()->json([
            'kind' => 'sheet',
            'bootstrap' => $payload['bootstrap'],
        ]);
    }

    public function board(Workspace $workspace, ?Dashboard $dashboard = null): JsonResponse
    {
        $workspace = $this->workspaceForUser($workspace->id);
        if ($dashboard) {
            abort_unless((int) $dashboard->workspace_id === (int) $workspace->id, 404);
        }

        return response()->json(WorkspaceShell::board($workspace, $dashboard));
    }

    public function people(Workspace $workspace): JsonResponse
    {
        $workspace = $this->workspaceForUser($workspace->id);
        $this->assertCanManage($workspace);

        return response()->json(WorkspaceShell::people($workspace));
    }

    public function automations(Workspace $workspace): JsonResponse
    {
        $workspace = $this->workspaceForUser($workspace->id);
        $this->assertCanBuild($workspace);

        return response()->json(WorkspaceShell::automations($workspace));
    }

    public function plan(Workspace $workspace): JsonResponse
    {
        $workspace = $this->workspaceForUser($workspace->id);
        $this->assertCanManage($workspace);

        return response()->json(WorkspaceShell::plan($workspace));
    }

    public function structure(Workspace $workspace): JsonResponse
    {
        $workspace = $this->workspaceForUser($workspace->id);
        $this->assertCanBuild($workspace);

        return response()->json(WorkspaceShell::structure($workspace));
    }

    public function switchSurface(Request $request, Workspace $workspace): JsonResponse
    {
        $workspace = $this->workspaceForUser($workspace->id);
        $data = $request->validate([
            'surface' => ['required', 'in:builder,app'],
        ]);
        if ($data['surface'] === 'builder' && ! Access::canBuild($request->user(), $workspace)) {
            abort(403);
        }
        $request->session()->put('surface', $data['surface']);
        $request->session()->put('workspace_id', $workspace->id);
        $workspace->load(['databases.tables', 'dashboards', 'plan', 'members']);

        return response()->json(WorkspaceShell::boot($request->user(), $workspace));
    }

    public function currentBoot(Request $request): JsonResponse
    {
        return response()->json(WorkspaceShell::boot($request->user(), $this->sessionWorkspace($request)));
    }

    public function currentSheet(Request $request, Table $table): JsonResponse
    {
        $workspace = $this->sessionWorkspace($request);
        $table = $this->tableForUser($table);
        abort_unless((int) $table->database->workspace_id === (int) $workspace->id, 404);

        $payload = TableBootstrap::make(
            $table,
            $request->user(),
            $workspace,
            $request->integer('view') ?: null,
            $request->string('search')->toString() ?: null,
        );

        return response()->json(['kind' => 'sheet', 'bootstrap' => $payload['bootstrap']]);
    }

    public function currentBoard(Request $request, ?Dashboard $dashboard = null): JsonResponse
    {
        $workspace = $this->sessionWorkspace($request);
        if ($dashboard) {
            abort_unless((int) $dashboard->workspace_id === (int) $workspace->id, 404);
        }

        return response()->json(WorkspaceShell::board($workspace, $dashboard));
    }

    public function currentPeople(Request $request): JsonResponse
    {
        $workspace = $this->sessionWorkspace($request);
        $this->assertCanManage($workspace);

        return response()->json(WorkspaceShell::people($workspace));
    }

    public function currentAutomations(Request $request): JsonResponse
    {
        $workspace = $this->sessionWorkspace($request);
        $this->assertCanBuild($workspace);

        return response()->json(WorkspaceShell::automations($workspace));
    }

    public function currentPlan(Request $request): JsonResponse
    {
        $workspace = $this->sessionWorkspace($request);
        $this->assertCanManage($workspace);

        return response()->json(WorkspaceShell::plan($workspace));
    }

    public function currentStructure(Request $request): JsonResponse
    {
        $workspace = $this->sessionWorkspace($request);
        $this->assertCanBuild($workspace);

        return response()->json(WorkspaceShell::structure($workspace));
    }

    public function currentSurface(Request $request): JsonResponse
    {
        $workspace = $this->sessionWorkspace($request);
        $data = $request->validate([
            'surface' => ['required', 'in:builder,app'],
        ]);
        if ($data['surface'] === 'builder' && ! Access::canBuild($request->user(), $workspace)) {
            abort(403);
        }
        $request->session()->put('surface', $data['surface']);
        $workspace->load(['databases.tables', 'dashboards', 'plan', 'members', 'children']);

        return response()->json(WorkspaceShell::boot($request->user(), $workspace));
    }

    private function sessionWorkspace(Request $request): Workspace
    {
        $id = $request->session()->get('workspace_id') ?: $request->user()->workspaces()->value('workspaces.id');
        abort_unless($id, 404);

        return $this->workspaceForUser($id);
    }
}
