<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Models\Dashboard;
use App\Models\Workspace;
use App\Support\Access;
use App\Support\DashboardData;
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
            if ($data['surface'] === 'app') {
                return redirect()->route('workspaces.app', $workspace);
            }

            return redirect()->route('workspaces.show', $workspace);
        }

        return back();
    }

    public function app(Workspace $workspace)
    {
        $workspace = $this->workspaceForUser($workspace->id);
        $workspace->load(['dashboards.widgets', 'databases.tables', 'plan', 'members']);
        $board = $workspace->dashboards->firstWhere('is_default') ?? $workspace->dashboards->first();
        $widgets = collect();
        if ($board) {
            $widgets = $board->widgets->map(fn ($widget) => [
                'model' => $widget,
                'data' => DashboardData::resolve($widget),
            ]);
        }

        return view('workspace.app', [
            'workspace' => $workspace,
            'workspaces' => auth()->user()->workspaces,
            'user' => auth()->user(),
            'role' => Access::role(auth()->user(), $workspace),
            'plan' => $workspace->resolvedPlan(),
            'dashboard' => $board,
            'widgets' => $widgets,
            'surface' => 'app',
        ]);
    }

    public function billing(Workspace $workspace)
    {
        $workspace = $this->workspaceForUser($workspace->id);
        $this->assertCanManage($workspace);

        return view('workspace.billing', [
            'workspace' => $workspace->load(['plan', 'members']),
            'workspaces' => auth()->user()->workspaces,
            'user' => auth()->user(),
            'role' => Access::role(auth()->user(), $workspace),
            'plan' => $workspace->resolvedPlan(),
            'plans' => \App\Models\Plan::ensureDefaults(),
        ]);
    }

    public function choosePlan(Request $request, Workspace $workspace)
    {
        $workspace = $this->workspaceForUser($workspace->id);
        $this->assertCanManage($workspace);
        $data = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
        ]);
        $workspace->update(['plan_id' => $data['plan_id']]);

        return back()->with('status', 'Workspace is now on the '.$workspace->fresh('plan')->plan->name.' plan.');
    }
}
