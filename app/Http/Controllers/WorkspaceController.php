<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Models\Workspace;
use App\Support\Access;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    use AuthorizesWorkspace;

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $workspace = Workspace::createForUser($request->user(), $data['name']);

        if ($request->expectsJson()) {
            return response()->json($workspace);
        }

        return redirect()->route('workspaces.show', $workspace);
    }

    public function show(Workspace $workspace)
    {
        $workspace = $this->workspaceForUser($workspace->id);
        $workspace->load(['databases.tables', 'members', 'plan', 'dashboards.widgets', 'automations']);
        $user = auth()->user();

        return view('workspace.shell', [
            'workspace' => $workspace,
            'workspaces' => $user->workspaces,
            'user' => $user,
            'role' => Access::role($user, $workspace),
            'plan' => $workspace->resolvedPlan(),
            'canBuild' => Access::canBuild($user, $workspace),
            'surface' => Access::surface($user, $workspace),
            'boot' => \App\Support\WorkspaceShell::boot($user, $workspace),
        ]);
    }

    public function update(Request $request, Workspace $workspace)
    {
        $this->workspaceForUser($workspace->id);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);
        $workspace->update($data);

        return $request->expectsJson()
            ? response()->json($workspace)
            : back();
    }

    public function destroy(Request $request, Workspace $workspace)
    {
        $this->workspaceForUser($workspace->id);
        $workspace->delete();

        return $request->expectsJson()
            ? response()->json(['ok' => true])
            : redirect()->route('dashboard');
    }
}
