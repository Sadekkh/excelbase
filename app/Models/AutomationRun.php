<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['automation_id', 'row_id', 'status', 'message'])]
class AutomationRun extends Model
{
    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    public function row(): BelongsTo
    {
        return $this->belongsTo(Row::class);
    }
}
