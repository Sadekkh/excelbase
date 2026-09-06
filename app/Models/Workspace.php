<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class Workspace extends Model
{
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function databases(): HasMany
    {
        return $this->hasMany(Database::class)->orderBy('order')->orderBy('name');
    }

    public static function createForUser(User $user, string $name): self
    {
        $workspace = self::create(['name' => $name]);
        $workspace->members()->attach($user->id, ['role' => 'admin']);

        return $workspace;
    }
}
