<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workspace_id', 'legal_name', 'address', 'siret', 'tva_number', 'ape', 'rcs',
    'email', 'phone', 'franchise_tva', 'default_vat', 'payment_days', 'late_penalty',
    'number_prefix', 'next_number', 'year', 'client_table_id',
])]
class InvoiceSetting extends Model
{
    protected $table = 'workspace_invoice_settings';

    protected function casts(): array
    {
        return [
            'franchise_tva' => 'boolean',
            'default_vat' => 'integer',
            'payment_days' => 'integer',
            'next_number' => 'integer',
            'year' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public static function for(Workspace $workspace): self
    {
        return self::query()->firstOrCreate(
            ['workspace_id' => $workspace->id],
            [
                'legal_name' => $workspace->name,
                'franchise_tva' => false,
                'default_vat' => 2000,
                'payment_days' => 30,
                'late_penalty' => '3 fois le taux d’intérêt légal',
                'number_prefix' => 'FA',
                'next_number' => 1,
                'year' => (int) now()->format('Y'),
            ]
        );
    }
}
