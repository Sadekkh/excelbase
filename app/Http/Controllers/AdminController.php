<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function index()
    {
        return view('admin.index', [
            'user' => auth()->user(),
            'stats' => [
                'workspaces' => Workspace::query()->count(),
                'users' => User::query()->count(),
                'plans' => Plan::query()->count(),
            ],
            'workspaces' => Workspace::query()->with(['plan', 'members'])->latest()->limit(8)->get(),
            'users' => User::query()->latest()->limit(8)->get(),
        ]);
    }

    public function workspaces()
    {
        return view('admin.workspaces', [
            'user' => auth()->user(),
            'workspaces' => Workspace::query()->with(['plan', 'members'])->latest()->get(),
            'plans' => Plan::ensureDefaults(),
        ]);
    }

    public function assignPlan(Request $request, Workspace $workspace)
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
        ]);
        $workspace->update(['plan_id' => $data['plan_id']]);

        return back()->with('status', 'Plan updated for '.$workspace->name.'.');
    }

    public function users()
    {
        return view('admin.users', [
            'user' => auth()->user(),
            'users' => User::query()->with('workspaces')->latest()->get(),
        ]);
    }

    public function toggleAdmin(User $user)
    {
        abort_if($user->id === auth()->id(), 422, 'You cannot change your own admin flag.');
        $user->update(['is_platform_admin' => ! $user->is_platform_admin]);

        return back()->with('status', $user->name.' admin access updated.');
    }

    public function plans()
    {
        return view('admin.plans', [
            'user' => auth()->user(),
            'plans' => Plan::ensureDefaults(),
        ]);
    }

    public function updatePlan(Request $request, Plan $plan)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'price_monthly' => ['required', 'integer', 'min:0'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'members' => ['required', 'integer', 'min:1'],
            'automations' => ['required', 'integer', 'min:0'],
            'dashboards' => ['required', 'integer', 'min:0'],
        ]);
        $features = $plan->features ?? [];
        $features['members'] = $data['members'];
        $features['automations'] = $data['automations'];
        $features['dashboards'] = $data['dashboards'];
        $plan->update([
            'name' => $data['name'],
            'price_monthly' => $data['price_monthly'],
            'tagline' => $data['tagline'],
            'features' => $features,
        ]);

        return back()->with('status', $plan->name.' saved.');
    }
}
