<?php

namespace App\Support;

use App\Models\User;
use App\Models\Workspace;

class Access
{
    public const ROLES = ['owner', 'admin', 'builder', 'member', 'viewer'];

    public static function role(?User $user, Workspace $workspace): ?string
    {
        if (! $user) {
            return null;
        }
        if ($workspace->relationLoaded('members')) {
            return $workspace->members->firstWhere('id', $user->id)?->pivot?->role;
        }

        return $workspace->members()->where('users.id', $user->id)->first()?->pivot?->role;
    }

    public static function canView(User $user, Workspace $workspace): bool
    {
        return $user->is_platform_admin || self::role($user, $workspace) !== null;
    }

    public static function canEditData(User $user, Workspace $workspace): bool
    {
        return in_array(self::role($user, $workspace), ['owner', 'admin', 'builder', 'member'], true)
            || $user->is_platform_admin;
    }

    public static function canBuild(User $user, Workspace $workspace): bool
    {
        return in_array(self::role($user, $workspace), ['owner', 'admin', 'builder'], true);
    }

    public static function canManage(User $user, Workspace $workspace): bool
    {
        return in_array(self::role($user, $workspace), ['owner', 'admin'], true);
    }

    public static function canOwn(User $user, Workspace $workspace): bool
    {
        return self::role($user, $workspace) === 'owner';
    }

    public static function surfaceKey(Workspace $workspace): string
    {
        return 'surface_'.$workspace->id;
    }

    public static function surface(User $user, Workspace $workspace): string
    {
        if (! self::canBuild($user, $workspace)) {
            return 'app';
        }

        return session(self::surfaceKey($workspace), 'app');
    }

    public static function setSurface(Workspace $workspace, string $surface): void
    {
        session()->put(self::surfaceKey($workspace), $surface);
    }

    public static function label(?string $role): string
    {
        return match ($role) {
            'owner' => 'Owner',
            'admin' => 'Admin',
            'builder' => 'Builder',
            'member' => 'Member',
            'viewer' => 'Viewer',
            default => 'Guest',
        };
    }
}
