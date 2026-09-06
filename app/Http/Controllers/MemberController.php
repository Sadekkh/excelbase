<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Access;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    use AuthorizesWorkspace;

    public function index(Workspace $workspace)
    {
        $workspace = $this->workspaceForUser($workspace->id);
        $this->assertCanManage($workspace);

        return redirect()->route('workspaces.show', $workspace);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $workspace = $this->workspaceForUser($workspace->id);
        $this->assertCanManage($workspace);
        $plan = $workspace->resolvedPlan();
        $roles = $plan->feature('roles') ? Access::ROLES : ['owner', 'member'];
        $data = $request->validate([
            'email' => ['required', 'email'],
            'name' => ['nullable', 'string', 'max:120'],
            'role' => ['required', Rule::in(array_values(array_filter($roles, fn ($r) => $r !== 'owner')))],
        ]);

        $limit = (int) $plan->feature('members', 3);
        if ($workspace->members()->count() >= $limit) {
            return $request->expectsJson()
                ? response()->json(['message' => 'This plan allows '.$limit.' members. Upgrade to add more.'], 422)
                : back()->withErrors(['email' => 'This plan allows '.$limit.' members. Upgrade to add more.']);
        }

        $member = User::query()->where('email', $data['email'])->first();
        $created = false;
        if (! $member) {
            $member = User::create([
                'name' => $data['name'] ?: strstr($data['email'], '@', true),
                'email' => $data['email'],
                'password' => 'password',
            ]);
            $created = true;
        }
        if ($workspace->members()->where('users.id', $member->id)->exists()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'That person is already in the workspace.'], 422)
                : back()->withErrors(['email' => 'That person is already in the workspace.']);
        }
        $workspace->members()->attach($member->id, ['role' => $data['role']]);

        $status = $created
            ? $member->name.' was invited. They can sign in with this email and password “password”.'
            : $member->name.' was added as '.Access::label($data['role']).'.';

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'status' => $status])
            : back()->with('status', $status);
    }

    public function update(Request $request, Workspace $workspace, User $user)
    {
        $workspace = $this->workspaceForUser($workspace->id);
        $this->assertCanManage($workspace);
        $plan = $workspace->resolvedPlan();
        $roles = $plan->feature('roles') ? Access::ROLES : ['owner', 'member'];
        $data = $request->validate([
            'role' => ['required', Rule::in($roles)],
        ]);
        if ($data['role'] !== 'owner' && $workspace->members()->wherePivot('role', 'owner')->count() <= 1) {
            $current = Access::role($user, $workspace);
            if ($current === 'owner') {
                return $request->expectsJson()
                    ? response()->json(['message' => 'Keep at least one owner.'], 422)
                    : back()->withErrors(['role' => 'Keep at least one owner.']);
            }
        }
        $workspace->members()->updateExistingPivot($user->id, ['role' => $data['role']]);

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'status' => $user->name.' is now '.Access::label($data['role']).'.'])
            : back()->with('status', $user->name.' is now '.Access::label($data['role']).'.');
    }

    public function destroy(Workspace $workspace, User $user)
    {
        $workspace = $this->workspaceForUser($workspace->id);
        $this->assertCanManage($workspace);
        abort_if($user->id === auth()->id(), 422, 'You cannot remove yourself.');
        abort_if(Access::role($user, $workspace) === 'owner', 422, 'Transfer ownership before removing an owner.');
        $workspace->members()->detach($user->id);

        return request()->expectsJson()
            ? response()->json(['ok' => true, 'status' => $user->name.' was removed.'])
            : back()->with('status', $user->name.' was removed.');
    }
}
