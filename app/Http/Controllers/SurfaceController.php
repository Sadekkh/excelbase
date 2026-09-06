<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Models\Workspace;
use App\Support\Access;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SurfaceController extends Controller
{
    use AuthorizesWorkspace;

    public function switch(Request $request)
    {
        $data = $request->validate([
            'surface' => ['required', Rule::in(['builder', 'app'])],
            'workspace_id' => ['nullable', 'integer'],
        ]);
        $request->session()->put('surface', $data['surface']);
        if ($data['workspace_id']) {
            $workspace = $this->workspaceForUser($data['workspace_id']);
            if ($request->expectsJson()) {
                $workspace->load(['databases.tables', 'dashboards', 'plan', 'members']);

                return response()->json(\App\Support\WorkspaceShell::boot($request->user(), $workspace));
            }

            return redirect()->route('workspaces.show', $workspace);
        }

        return $request->expectsJson()
            ? response()->json(['surface' => $data['surface']])
            : back();
    }

    public function app(Workspace $workspace)
    {
        $workspace = $this->workspaceForUser($workspace->id);

        return redirect()->route('workspaces.show', $workspace);
    }

    public function billing(Workspace $workspace)
    {
        $workspace = $this->workspaceForUser($workspace->id);
        $this->assertCanManage($workspace);

        return redirect()->route('workspaces.show', $workspace);
    }

    public function choosePlan(Request $request, Workspace $workspace)
    {
        $workspace = $this->workspaceForUser($workspace->id);
        $this->assertCanManage($workspace);
        $data = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
        ]);
        $workspace->update(['plan_id' => $data['plan_id']]);
        $name = $workspace->fresh('plan')->plan->name;

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'status' => 'Workspace is now on the '.$name.' plan.'])
            : back()->with('status', 'Workspace is now on the '.$name.' plan.');
    }
}
