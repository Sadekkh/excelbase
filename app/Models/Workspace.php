<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'plan_id', 'parent_id'])]
class Workspace extends Model
{
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function databases(): HasMany
    {
        return $this->hasMany(Database::class)->orderBy('order')->orderBy('name');
    }

    public function dashboards(): HasMany
    {
        return $this->hasMany(Dashboard::class)->orderBy('order')->orderBy('name');
    }

    public function automations(): HasMany
    {
        return $this->hasMany(Automation::class)->orderBy('name');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function resolvedPlan(): Plan
    {
        return $this->plan ?? Plan::free();
    }

    /**
     * @return list<array{id: int, name: string, parent_id: int|null, depth: int, children: list<array<string, mixed>>}>
     */
    public static function treeFor(User $user): array
    {
        $memberships = $user->workspaces()->with(['children', 'parent'])->get();
        $reachable = collect();
        foreach ($memberships as $workspace) {
            $reachable[$workspace->id] = $workspace;
            foreach (self::descendantsOf($workspace) as $child) {
                $reachable[$child->id] = $child;
            }
        }
        $byParent = $reachable->groupBy(fn ($ws) => $ws->parent_id ?: 0);

        $build = function ($parentId, $depth) use (&$build, $byParent) {
            return ($byParent[$parentId] ?? collect())->sortBy('name')->values()->map(function ($ws) use ($build, $depth) {
                return [
                    'id' => $ws->id,
                    'name' => $ws->name,
                    'parent_id' => $ws->parent_id,
                    'depth' => $depth,
                    'children' => $build($ws->id, $depth + 1),
                ];
            })->all();
        };

        return $build(0, 0);
    }

    /**
     * @return list<self>
     */
    public static function descendantsOf(self $workspace): array
    {
        $workspace->loadMissing('children');
        $out = [];
        foreach ($workspace->children as $child) {
            $out[] = $child;
            foreach (self::descendantsOf($child) as $nested) {
                $out[] = $nested;
            }
        }

        return $out;
    }

    public static function createForUser(User $user, string $name, ?Plan $plan = null, ?self $parent = null): self
    {
        Plan::ensureDefaults();
        $plan ??= $parent?->resolvedPlan() ?? Plan::free();
        $workspace = self::create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'plan_id' => $plan->id,
            'parent_id' => $parent?->id,
        ]);
        $workspace->members()->attach($user->id, ['role' => 'owner']);
        if ($parent) {
            foreach ($parent->members as $member) {
                if ((int) $member->id === (int) $user->id) {
                    continue;
                }
                $workspace->members()->syncWithoutDetaching([
                    $member->id => ['role' => $member->pivot->role],
                ]);
            }
        }

        return $workspace->fresh(['plan', 'members', 'parent']);
    }
}
