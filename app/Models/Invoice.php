<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workspace_id', 'number', 'kind', 'status', 'client_name', 'client_address',
    'client_email', 'client_siret', 'issue_date', 'due_date', 'paid_at', 'lines',
    'total_ht', 'total_tva', 'total_ttc', 'notes', 'source_template', 'client_row_id',
])]
class Invoice extends Model
{
    protected function casts(): array
    {
        return [
            'lines' => 'array',
            'issue_date' => 'date',
            'due_date' => 'date',
            'paid_at' => 'date',
            'total_ht' => 'integer',
            'total_tva' => 'integer',
            'total_ttc' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function cents(string $field): string
    {
        return \App\Support\Erp\Money::format((int) $this->{$field});
    }
}
