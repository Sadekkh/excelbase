<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Models\Automation;
use App\Models\Workspace;
use App\Support\Access;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AutomationController extends Controller
{
    use AuthorizesWorkspace;

    public function index(Workspace $workspace)
    {
        $workspace = $this->workspaceForUser($workspace->id);
        $this->assertCanBuild($workspace);

        return redirect()->route('workspaces.show', $workspace);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $workspace = $this->workspaceForUser($workspace->id);
        $this->assertCanBuild($workspace);
        $plan = $workspace->resolvedPlan();
        if ($workspace->automations()->count() >= (int) $plan->feature('automations', 0)) {
            return $request->expectsJson()
                ? response()->json(['message' => 'This plan allows '.$plan->feature('automations').' automations.'], 422)
                : back()->withErrors(['name' => 'This plan allows '.$plan->feature('automations').' automations.']);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'table_id' => ['required', 'integer'],
            'trigger' => ['required', Rule::in(['row_created', 'row_updated', 'field_changed'])],
            'trigger_field_id' => ['nullable', 'integer'],
            'if_field_id' => ['nullable', 'integer'],
            'if_value' => ['nullable', 'string', 'max:240'],
            'action' => ['required', Rule::in(['update_field', 'create_row', 'notify'])],
            'action_field_id' => ['nullable', 'integer'],
            'action_value' => ['nullable', 'string', 'max:240'],
            'action_table_id' => ['nullable', 'integer'],
            'notify_role' => ['nullable', 'string', 'max:40'],
            'notify_message' => ['nullable', 'string', 'max:240'],
        ]);
        abort_unless($workspace->databases()->whereHas('tables', fn ($q) => $q->whereKey($data['table_id']))->exists(), 422);

        $workspace->automations()->create([
            'table_id' => $data['table_id'],
            'name' => $data['name'],
            'enabled' => true,
            'trigger' => $data['trigger'],
            'trigger_config' => [
                'field_id' => $data['trigger_field_id'] ?? null,
                'if_field_id' => $data['if_field_id'] ?? null,
                'if_value' => $data['if_value'] ?? null,
            ],
            'action' => $data['action'],
            'action_config' => [
                'field_id' => $data['action_field_id'] ?? null,
                'value' => $data['action_value'] ?? null,
                'table_id' => $data['action_table_id'] ?? null,
                'role' => $data['notify_role'] ?? 'builders',
                'message' => $data['notify_message'] ?? $data['name'],
            ],
        ]);

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'status' => 'Automation “'.$data['name'].'” is on.'])
            : back()->with('status', 'Automation “'.$data['name'].'” is on.');
    }

    public function update(Request $request, Workspace $workspace, Automation $automation)
    {
        $workspace = $this->workspaceForUser($workspace->id);
        $this->assertCanBuild($workspace);
        abort_unless($automation->workspace_id === $workspace->id, 404);
        $data = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
        ]);
        $automation->update($data);

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'status' => $automation->name.' updated.'])
            : back()->with('status', $automation->name.' updated.');
    }

    public function destroy(Workspace $workspace, Automation $automation)
    {
        $workspace = $this->workspaceForUser($workspace->id);
        $this->assertCanBuild($workspace);
        abort_unless($automation->workspace_id === $workspace->id, 404);
        $automation->delete();

        return request()->expectsJson()
            ? response()->json(['ok' => true, 'status' => 'Automation deleted.'])
            : back()->with('status', 'Automation deleted.');
    }
}
