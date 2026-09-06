<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'workspace_id', 'table_id', 'name', 'enabled',
    'trigger', 'trigger_config', 'action', 'action_config',
])]
class Automation extends Model
{
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'trigger_config' => 'array',
            'action_config' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class)->latest();
    }
}
