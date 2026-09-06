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
        $name = $workspace->fresh('plan')->plan->name;

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'status' => 'Workspace is now on the '.$name.' plan.'])
            : back()->with('status', 'Workspace is now on the '.$name.' plan.');
    }
}
