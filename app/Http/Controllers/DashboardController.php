<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;

class DashboardController extends Controller
{
    use AuthorizesWorkspace;

    public function __invoke()
    {
        return redirect()->route('app');
    }
}
