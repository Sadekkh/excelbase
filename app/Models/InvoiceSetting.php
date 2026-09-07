<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workspace_id', 'legal_name', 'address', 'siret', 'tva_number', 'ape', 'rcs',
    'email', 'phone', 'franchise_tva', 'default_vat', 'payment_days', 'late_penalty',
    'number_prefix', 'next_number', 'year', 'client_table_id', 'template',
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
            'template' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public static function for(Workspace $workspace): self
    {
        $settings = self::query()->firstOrCreate(
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
        $settings->setRelation('workspace', $workspace);

        return $settings;
    }

    /**
     * @return array{
     *   title: string,
     *   accent: string,
     *   intro: string,
     *   footer: string,
     *   legal: string,
     *   show_logo: bool,
     *   show_due_date: bool
     * }
     */
    public function resolvedTemplate(): array
    {
        $stored = is_array($this->template) ? $this->template : [];
        $accent = (string) ($stored['accent'] ?? '');
        if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $accent)) {
            $accent = $this->workspace?->brand_color ?: '#5190ef';
            if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $accent)) {
                $accent = '#5190ef';
            }
        }

        return [
            'title' => (string) ($stored['title'] ?? 'Facture'),
            'accent' => $accent,
            'intro' => (string) ($stored['intro'] ?? ''),
            'footer' => (string) ($stored['footer'] ?? ''),
            'legal' => (string) ($stored['legal'] ?? ''),
            'show_logo' => array_key_exists('show_logo', $stored) ? (bool) $stored['show_logo'] : true,
            'show_due_date' => array_key_exists('show_due_date', $stored) ? (bool) $stored['show_due_date'] : true,
        ];
    }

    public function documentTitle(Invoice $invoice): string
    {
        return match ($invoice->kind) {
            'avoir' => 'Avoir',
            'devis' => 'Devis',
            default => $this->resolvedTemplate()['title'] ?: 'Facture',
        };
    }

    public function vatRatePercent(): float
    {
        if ($this->franchise_tva) {
            return 0.0;
        }

        return ((int) $this->default_vat) / 100;
    }

    public function vatRateBps(): int
    {
        return $this->franchise_tva ? 0 : (int) $this->default_vat;
    }
}
