<?php

namespace App\Support;

use App\Models\Dashboard;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;

class WorkspaceShell
{
    /**
     * @return array<string, mixed>
     */
    public static function boot(User $user, Workspace $workspace): array
    {
        $workspace->loadMissing(['databases.tables', 'dashboards.widgets', 'plan', 'members', 'automations']);
        $surface = Access::surface($user, $workspace);
        $canBuild = Access::canBuild($user, $workspace);
        $plan = $workspace->resolvedPlan();
        $firstTable = $workspace->databases->flatMap->tables->first();
        $board = $workspace->dashboards->firstWhere('is_default') ?? $workspace->dashboards->first();

        return [
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
            ],
            'workspaces' => $user->workspaces()->orderBy('name')->get()->map(fn ($ws) => [
                'id' => $ws->id,
                'name' => $ws->name,
                'url' => route('workspaces.show', $ws),
            ])->values(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'initials' => strtoupper(substr($user->name, 0, 1)),
                'is_platform_admin' => (bool) $user->is_platform_admin,
            ],
            'role' => Access::role($user, $workspace),
            'role_label' => Access::label(Access::role($user, $workspace)),
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
            ],
            'surface' => $surface,
            'can_build' => $canBuild,
            'can_manage' => Access::canManage($user, $workspace),
            'can_edit' => Access::canEditData($user, $workspace),
            'unread' => $user->notifications()->whereNull('read_at')->count(),
            'databases' => $workspace->databases->map(fn ($db) => [
                'id' => $db->id,
                'name' => $db->name,
                'tables' => $db->tables->map(fn ($tbl) => [
                    'id' => $tbl->id,
                    'name' => $tbl->name,
                ])->values(),
            ])->values(),
            'dashboards' => $workspace->dashboards->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'is_default' => (bool) $item->is_default,
            ])->values(),
            'start' => [
                'kind' => $firstTable ? 'sheet' : ($board ? 'board' : 'empty'),
                'table_id' => $firstTable?->id,
                'dashboard_id' => $board?->id,
            ],
            'urls' => [
                'boot' => route('workspaces.panel.boot', $workspace),
                'sheet' => url('/workspace/'.$workspace->id.'/panel/sheet'),
                'board' => url('/workspace/'.$workspace->id.'/panel/board'),
                'people' => route('workspaces.panel.people', $workspace),
                'automations' => route('workspaces.panel.automations', $workspace),
                'plan' => route('workspaces.panel.plan', $workspace),
                'structure' => route('workspaces.panel.structure', $workspace),
                'surface' => route('surface.switch'),
                'databaseStore' => route('databases.store', $workspace),
                'memberStore' => route('members.store', $workspace),
                'automationStore' => route('automations.store', $workspace),
                'planChoose' => route('workspaces.plan', $workspace),
                'logout' => route('logout'),
                'home' => route('dashboard'),
                'inbox' => route('notifications.index'),
                'admin' => route('admin.index'),
                'csrf' => csrf_token(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function board(Workspace $workspace, ?Dashboard $board = null): array
    {
        $workspace->loadMissing(['dashboards.widgets']);
        $board = $board
            ?? $workspace->dashboards->firstWhere('is_default')
            ?? $workspace->dashboards->first();
        $widgets = collect();
        if ($board) {
            $widgets = $board->widgets->map(fn ($widget) => [
                'id' => $widget->id,
                'type' => $widget->type,
                'title' => $widget->title,
                'data' => DashboardData::resolve($widget),
            ])->values();
        }

        return [
            'kind' => 'board',
            'dashboard' => $board ? [
                'id' => $board->id,
                'name' => $board->name,
                'description' => $board->description,
            ] : null,
            'widgets' => $widgets,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function people(Workspace $workspace): array
    {
        $workspace->loadMissing(['members', 'plan']);
        $plan = $workspace->resolvedPlan();
        $roles = $plan->feature('roles') ? Access::ROLES : ['owner', 'member'];

        return [
            'kind' => 'people',
            'members' => $workspace->members->map(fn ($member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->pivot->role,
                'role_label' => Access::label($member->pivot->role),
            ])->values(),
            'roles' => collect($roles)->map(fn ($role) => [
                'id' => $role,
                'label' => Access::label($role),
            ])->values(),
            'invite_roles' => collect($roles)->filter(fn ($role) => $role !== 'owner')->values(),
            'seats' => $workspace->members->count(),
            'seat_limit' => $plan->feature('members'),
            'plan_name' => $plan->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function automations(Workspace $workspace): array
    {
        $workspace->loadMissing(['automations.table', 'automations.runs', 'databases.tables.fields', 'plan']);
        $plan = $workspace->resolvedPlan();
        $tables = [];
        $fields = [];
        foreach ($workspace->databases as $database) {
            foreach ($database->tables as $table) {
                $tables[] = [
                    'id' => $table->id,
                    'name' => $database->name.' / '.$table->name,
                ];
                foreach ($table->fields as $field) {
                    $fields[] = [
                        'id' => $field->id,
                        'name' => $table->name.' · '.$field->name,
                    ];
                }
            }
        }

        return [
            'kind' => 'automations',
            'used' => $workspace->automations->count(),
            'limit' => $plan->feature('automations'),
            'plan_name' => $plan->name,
            'tables' => $tables,
            'fields' => $fields,
            'automations' => $workspace->automations->map(function ($auto) {
                $run = $auto->runs->first();

                return [
                    'id' => $auto->id,
                    'name' => $auto->name,
                    'enabled' => (bool) $auto->enabled,
                    'table' => $auto->table?->name,
                    'trigger' => str_replace('_', ' ', $auto->trigger),
                    'action' => str_replace('_', ' ', $auto->action),
                    'last_run' => $run ? $run->status.' — '.$run->message : null,
                ];
            })->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function plan(Workspace $workspace): array
    {
        $current = $workspace->resolvedPlan();

        return [
            'kind' => 'plan',
            'current_id' => $current->id,
            'plans' => Plan::ensureDefaults()->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'price_monthly' => $item->price_monthly,
                'tagline' => $item->tagline,
                'members' => $item->feature('members'),
                'automations' => $item->feature('automations'),
                'dashboards' => $item->feature('dashboards'),
                'views' => implode(', ', $item->feature('views', [])),
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function structure(Workspace $workspace): array
    {
        $workspace->loadMissing(['databases.tables']);

        return [
            'kind' => 'structure',
            'databases' => $workspace->databases->map(fn ($db) => [
                'id' => $db->id,
                'name' => $db->name,
                'tables' => $db->tables->map(fn ($tbl) => [
                    'id' => $tbl->id,
                    'name' => $tbl->name,
                    'fields' => $tbl->fields()->count(),
                ])->values(),
            ])->values(),
        ];
    }
}
