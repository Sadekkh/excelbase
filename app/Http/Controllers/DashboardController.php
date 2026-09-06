<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;

class DashboardController extends Controller
{
    use AuthorizesWorkspace;

    public function __invoke()
    {
        $workspaces = auth()->user()->workspaces()->with(['databases.tables', 'members', 'plan'])->get();

        return view('dashboard.index', [
            'workspaces' => $workspaces,
            'user' => auth()->user(),
            'unread' => auth()->user()->notifications()->whereNull('read_at')->count(),
        ]);
    }
}
