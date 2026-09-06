<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workspace_id', 'name', 'order'])]
class Database extends Model
{
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function tables(): HasMany
    {
        return $this->hasMany(Table::class)->orderBy('order')->orderBy('name');
    }

    public static function createWithTable(Workspace $workspace, string $name, ?string $tableName = 'Table'): self
    {
        $order = ((int) $workspace->databases()->max('order')) + 1;
        $database = self::create([
            'workspace_id' => $workspace->id,
            'name' => $name,
            'order' => $order,
        ]);

        Table::createWithDefaults($database, $tableName ?? 'Table');

        return $database;
    }
}
