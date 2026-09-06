<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Models\Workspace;
use App\Support\WorkspaceShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
                'boot' => WorkspaceShell::boot($request->user(), $workspace),
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
        $workspace = $this->workspaceForUser($workspace->id);
        $this->assertCanBuild($workspace);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);
        $workspace->update($data);

        return $request->expectsJson()
            ? response()->json($workspace)
            : back();
    }

    public function appearance(Request $request, Workspace $workspace)
    {
        $workspace = $this->workspaceForUser($workspace->id);
        $this->assertCanBuild($workspace);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'brand_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sidebar_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'remove_logo' => ['nullable'],
        ]);

        $workspace->name = $data['name'];
        $workspace->tagline = $data['tagline'] ?? null;
        $workspace->brand_color = $data['brand_color'] ?? null;
        $workspace->sidebar_color = $data['sidebar_color'] ?? null;

        if ($request->boolean('remove_logo') && $workspace->logo_path) {
            Storage::disk('public')->delete($workspace->logo_path);
            $workspace->logo_path = null;
        }

        if ($request->hasFile('logo')) {
            if ($workspace->logo_path) {
                Storage::disk('public')->delete($workspace->logo_path);
            }
            $workspace->logo_path = $request->file('logo')->store('workspace-logos', 'public');
        }

        $workspace->save();

        return $request->expectsJson()
            ? response()->json([
                'ok' => true,
                'status' => 'Look saved for '.$workspace->name.'.',
                'workspace' => $workspace->fresh()->brandPayload(),
                'boot' => WorkspaceShell::boot($request->user(), $workspace->fresh(['databases.tables', 'dashboards', 'plan', 'members', 'children'])),
            ])
            : back()->with('status', 'Look saved for '.$workspace->name.'.');
    }

    public function destroy(Request $request, Workspace $workspace)
    {
        $workspace = $this->workspaceForUser($workspace->id);
        $this->assertCanManage($workspace);
        $workspace->delete();

        return $request->expectsJson()
            ? response()->json(['ok' => true])
            : redirect()->route('dashboard');
    }
}
