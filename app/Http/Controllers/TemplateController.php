<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Support\Erp\BuildAssistant;
use App\Support\Erp\TemplateInstaller;
use App\Support\WorkspaceShell;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    use AuthorizesWorkspace;

    public function index(Request $request)
    {
        $workspace = $this->sessionWorkspace($request);
        $this->assertCanBuild($workspace);

        return response()->json([
            'kind' => 'templates',
            'templates' => TemplateInstaller::catalog($workspace),
        ]);
    }

    public function install(Request $request)
    {
        $workspace = $this->sessionWorkspace($request);
        $this->assertCanBuild($workspace);
        $data = $request->validate(['slug' => ['required', 'string', 'max:80']]);
        try {
            $installed = TemplateInstaller::install($workspace, $data['slug']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        $workspace->load(['databases.tables', 'dashboards', 'plan', 'members', 'children', 'templates']);

        return response()->json([
            'ok' => true,
            'status' => $installed->name.' is installed. Adjust anything in Build.',
            'install_id' => $installed->id,
            'boot' => WorkspaceShell::boot($request->user(), $workspace),
        ]);
    }

    public function destroy(Request $request, string $slug)
    {
        $workspace = $this->sessionWorkspace($request);
        $this->assertCanBuild($workspace);
        try {
            TemplateInstaller::uninstall($workspace, $slug);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        $workspace->load(['databases.tables', 'dashboards', 'plan', 'members', 'children', 'templates']);

        return response()->json([
            'ok' => true,
            'status' => 'Template removed. Invoices were kept.',
            'boot' => WorkspaceShell::boot($request->user(), $workspace),
        ]);
    }

    public function assistant(Request $request)
    {
        $workspace = $this->sessionWorkspace($request);
        $this->assertCanBuild($workspace);
        $data = $request->validate(['prompt' => ['required', 'string', 'max:2000']]);

        return response()->json([
            'kind' => 'assistant',
            'plan' => BuildAssistant::plan($data['prompt']),
        ]);
    }

    public function applyAssistant(Request $request)
    {
        $workspace = $this->sessionWorkspace($request);
        $this->assertCanBuild($workspace);
        $data = $request->validate([
            'kind' => ['required', 'string'],
            'slug' => ['nullable', 'string'],
            'database' => ['nullable', 'string', 'max:120'],
            'tables' => ['nullable', 'array'],
            'prompt' => ['nullable', 'string', 'max:2000'],
        ]);
        $plan = $data;
        if (($plan['kind'] ?? '') === 'schema' && empty($plan['tables']) && ! empty($plan['prompt'])) {
            $plan = BuildAssistant::plan($plan['prompt']);
        }
        $created = BuildAssistant::apply($workspace, $plan);
        $workspace->load(['databases.tables', 'dashboards', 'plan', 'members', 'children', 'templates']);

        return response()->json([
            'ok' => true,
            'status' => 'Structure created. Open the new tables in Build to refine logic and links.',
            'created' => $created,
            'boot' => WorkspaceShell::boot($request->user(), $workspace),
            'table_id' => $created['table_ids'][0] ?? null,
        ]);
    }

    private function sessionWorkspace(Request $request)
    {
        $id = $request->session()->get('workspace_id') ?: $request->user()->workspaces()->value('workspaces.id');
        abort_unless($id, 404);

        return $this->workspaceForUser($id);
    }
}
