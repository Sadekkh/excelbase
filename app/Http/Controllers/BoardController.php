<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\Workspace;
use App\Support\Access;
use App\Support\DashboardData;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BoardController extends Controller
{
    use AuthorizesWorkspace;

    public function index(Workspace $workspace)
    {
        $workspace = $this->workspaceForUser($workspace->id);
        $workspace->load(['dashboards.widgets', 'databases.tables.fields', 'plan', 'members']);

        return view('dashboard.boards', [
            'workspace' => $workspace,
            'workspaces' => auth()->user()->workspaces,
            'user' => auth()->user(),
            'role' => Access::role(auth()->user(), $workspace),
            'plan' => $workspace->resolvedPlan(),
            'canBuild' => Access::canBuild(auth()->user(), $workspace),
            'surface' => Access::surface(auth()->user(), $workspace),
        ]);
    }

    public function show(Workspace $workspace, Dashboard $dashboard)
    {
        $workspace = $this->workspaceForUser($workspace->id);
        abort_unless($dashboard->workspace_id === $workspace->id, 404);
        $dashboard->load('widgets');
        $workspace->load(['databases.tables.fields', 'plan', 'members']);
        $widgets = $dashboard->widgets->map(function (DashboardWidget $widget) {
            return [
                'model' => $widget,
                'data' => DashboardData::resolve($widget),
            ];
        });

        return view('dashboard.board', [
            'workspace' => $workspace,
            'workspaces' => auth()->user()->workspaces,
            'user' => auth()->user(),
            'role' => Access::role(auth()->user(), $workspace),
            'plan' => $workspace->resolvedPlan(),
            'canBuild' => Access::canBuild(auth()->user(), $workspace),
            'surface' => Access::surface(auth()->user(), $workspace),
            'dashboard' => $dashboard,
            'widgets' => $widgets,
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $workspace = $this->workspaceForUser($workspace->id);
        $this->assertCanBuild($workspace);
        $plan = $workspace->resolvedPlan();
        if ($workspace->dashboards()->count() >= (int) $plan->feature('dashboards', 1)) {
            return back()->withErrors(['name' => 'This plan allows '.$plan->feature('dashboards').' dashboards.']);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:240'],
        ]);
        $board = $workspace->dashboards()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'is_default' => $workspace->dashboards()->count() === 0,
            'order' => ((int) $workspace->dashboards()->max('order')) + 1,
        ]);

        return redirect()->route('dashboards.show', [$workspace, $board]);
    }

    public function storeWidget(Request $request, Workspace $workspace, Dashboard $dashboard)
    {
        $workspace = $this->workspaceForUser($workspace->id);
        $this->assertCanBuild($workspace);
        abort_unless($dashboard->workspace_id === $workspace->id, 404);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(['stat', 'chart', 'list'])],
            'table_id' => ['required', 'integer'],
            'field_id' => ['nullable', 'integer'],
            'metric' => ['nullable', Rule::in(['count', 'sum'])],
        ]);
        $dashboard->widgets()->create([
            'title' => $data['title'],
            'type' => $data['type'],
            'config' => [
                'table_id' => $data['table_id'],
                'field_id' => $data['field_id'] ?? null,
                'metric' => $data['metric'] ?? 'count',
            ],
            'order' => ((int) $dashboard->widgets()->max('order')) + 1,
        ]);

        return back()->with('status', 'Widget added.');
    }

    public function destroyWidget(Workspace $workspace, Dashboard $dashboard, DashboardWidget $widget)
    {
        $workspace = $this->workspaceForUser($workspace->id);
        $this->assertCanBuild($workspace);
        abort_unless($dashboard->workspace_id === $workspace->id && $widget->dashboard_id === $dashboard->id, 404);
        $widget->delete();

        return back()->with('status', 'Widget removed.');
    }

    public function destroy(Workspace $workspace, Dashboard $dashboard)
    {
        $workspace = $this->workspaceForUser($workspace->id);
        $this->assertCanBuild($workspace);
        abort_unless($dashboard->workspace_id === $workspace->id, 404);
        $dashboard->delete();

        return redirect()->route('dashboards.index', $workspace)->with('status', 'Dashboard deleted.');
    }
}
