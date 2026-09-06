<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['dashboard_id', 'type', 'title', 'config', 'order'])]
class DashboardWidget extends Model
{
    protected function casts(): array
    {
        return [
            'config' => 'array',
        ];
    }

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }
}
