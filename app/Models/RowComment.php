<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['row_id', 'user_id', 'body'])]
class RowComment extends Model
{
    public function row(): BelongsTo
    {
        return $this->belongsTo(Row::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
