<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'plan_id'])]
class Workspace extends Model
{
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

    public static function createForUser(User $user, string $name, ?Plan $plan = null): self
    {
        Plan::ensureDefaults();
        $plan ??= Plan::free();
        $workspace = self::create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'plan_id' => $plan->id,
        ]);
        $workspace->members()->attach($user->id, ['role' => 'owner']);

        return $workspace->fresh(['plan', 'members']);
    }
}
