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
            'parent_id' => ['nullable', 'integer'],
        ]);
        $parent = null;
        if (! empty($data['parent_id'])) {
            $parent = $this->workspaceForUser($data['parent_id']);
            $this->assertCanBuild($parent);
        }

        $workspace = Workspace::createForUser($request->user(), $data['name'], $parent?->resolvedPlan(), $parent);
        $request->session()->put('workspace_id', $workspace->id);

        if ($request->expectsJson()) {
            return response()->json(array_merge($workspace->toArray(), [
                'boot' => \App\Support\WorkspaceShell::boot($request->user(), $workspace),
            ]));
        }

        return redirect()->route('app');
    }

    public function show(Workspace $workspace)
    {
        $workspace = $this->workspaceForUser($workspace->id);
        session(['workspace_id' => $workspace->id]);

        return redirect()->route('app');
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
