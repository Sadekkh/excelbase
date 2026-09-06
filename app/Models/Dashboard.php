<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workspace_id', 'name', 'description', 'is_default', 'order'])]
class Dashboard extends Model
{
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function widgets(): HasMany
    {
        return $this->hasMany(DashboardWidget::class)->orderBy('order');
    }
}
