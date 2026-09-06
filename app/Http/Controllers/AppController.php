<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Models\Workspace;
use App\Support\Access;
use App\Support\WorkspaceShell;
use Illuminate\Http\Request;

class AppController extends Controller
{
    use AuthorizesWorkspace;

    public function __invoke(Request $request)
    {
        $user = $request->user();
        $workspace = $this->currentWorkspace($request, $user);
        $request->session()->put('workspace_id', $workspace->id);
        $workspace->load(['databases.tables', 'members', 'plan', 'dashboards.widgets', 'automations', 'children']);

        return view('workspace.shell', [
            'workspace' => $workspace,
            'workspaces' => $user->workspaces,
            'user' => $user,
            'role' => Access::role($user, $workspace),
            'plan' => $workspace->resolvedPlan(),
            'canBuild' => Access::canBuild($user, $workspace),
            'surface' => Access::surface($user, $workspace),
            'boot' => WorkspaceShell::boot($user, $workspace),
        ]);
    }

    public function open(Request $request)
    {
        $data = $request->validate([
            'workspace_id' => ['required', 'integer'],
        ]);
        $workspace = $this->workspaceForUser($data['workspace_id']);
        $request->session()->put('workspace_id', $workspace->id);
        $workspace->load(['databases.tables', 'dashboards', 'plan', 'members', 'children']);

        return response()->json(WorkspaceShell::boot($request->user(), $workspace));
    }

    private function currentWorkspace(Request $request, $user): Workspace
    {
        $id = $request->session()->get('workspace_id');
        if ($id) {
            try {
                return $this->workspaceForUser($id);
            } catch (\Illuminate\Auth\Access\AuthorizationException) {
                // fall through to first membership
            }
        }
        $workspace = $user->workspaces()->with(['plan', 'members'])->first();
        if (! $workspace) {
            $workspace = Workspace::createForUser($user, $user->name."'s workspace");
        }

        return $workspace;
    }
}
