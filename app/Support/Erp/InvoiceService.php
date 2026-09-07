<?php

namespace App\Support\Erp;

use App\Models\Invoice;
use App\Models\InvoiceSetting;
use App\Models\Workspace;
use Illuminate\Support\Carbon;

class InvoiceService
{
    /**
     * Line tax always comes from invoice settings (franchise → 0).
     *
     * @param  list<array{description?: string, qty?: float|int|string, unit_price?: float|int|string, vat?: float|int|string}>  $lines
     * @return array{lines: list<array<string, mixed>>, total_ht: int, total_tva: int, total_ttc: int}
     */
    public static function totals(array $lines, InvoiceSetting $settings): array
    {
        $normalized = [];
        $ht = 0;
        $tva = 0;
        $vatBps = $settings->vatRateBps();
        foreach ($lines as $line) {
            $qty = (float) str_replace(',', '.', (string) ($line['qty'] ?? 1));
            $unit = Money::fromDecimal($line['unit_price'] ?? 0);
            $lineHt = (int) round($qty * $unit);
            $lineTva = (int) round($lineHt * $vatBps / 10000);
            $ht += $lineHt;
            $tva += $lineTva;
            $normalized[] = [
                'description' => (string) ($line['description'] ?? ''),
                'qty' => $qty,
                'unit_price' => $unit,
                'vat' => $vatBps,
                'ht' => $lineHt,
                'tva' => $lineTva,
                'ttc' => $lineHt + $lineTva,
            ];
        }

        return [
            'lines' => $normalized,
            'total_ht' => $ht,
            'total_tva' => $tva,
            'total_ttc' => $ht + $tva,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function save(Workspace $workspace, array $payload, ?Invoice $invoice = null): Invoice
    {
        $settings = InvoiceSetting::for($workspace);
        $computed = self::totals($payload['lines'] ?? [], $settings);
        $invoice ??= new Invoice(['workspace_id' => $workspace->id]);
        $issued = in_array($invoice->status, ['issued', 'paid'], true);
        $invoice->fill([
            'kind' => $payload['kind'] ?? $invoice->kind ?? 'facture',
            'client_name' => $payload['client_name'],
            'client_address' => $payload['client_address'] ?? null,
            'client_email' => $payload['client_email'] ?? null,
            'client_siret' => $payload['client_siret'] ?? null,
            'issue_date' => $payload['issue_date'] ?? $invoice->issue_date ?? now()->toDateString(),
            'due_date' => $payload['due_date'] ?? $invoice->due_date ?? now()->addDays($settings->payment_days)->toDateString(),
            'notes' => $payload['notes'] ?? null,
            'client_row_id' => $payload['client_row_id'] ?? null,
            'source_template' => $invoice->source_template ?? $payload['source_template'] ?? null,
            'lines' => $computed['lines'],
            'total_ht' => $computed['total_ht'],
            'total_tva' => $computed['total_tva'],
            'total_ttc' => $computed['total_ttc'],
        ]);
        if (! $issued && empty($invoice->status)) {
            $invoice->status = 'draft';
        }
        $invoice->save();

        return $invoice->fresh();
    }

    public static function issue(Invoice $invoice): Invoice
    {
        if ($invoice->status !== 'draft') {
            return $invoice;
        }
        $settings = InvoiceSetting::for($invoice->workspace);
        $year = (int) now()->format('Y');
        if ((int) $settings->year !== $year) {
            $settings->year = $year;
            $settings->next_number = 1;
        }
        $invoice->number = sprintf('%s-%d-%04d', $settings->number_prefix ?: 'FA', $year, $settings->next_number);
        $invoice->status = 'issued';
        $invoice->issue_date = $invoice->issue_date ?? Carbon::now();
        $invoice->save();
        $settings->next_number++;
        $settings->save();

        return $invoice->fresh();
    }

    public static function markPaid(Invoice $invoice): Invoice
    {
        if ($invoice->status === 'draft') {
            $invoice = self::issue($invoice);
        }
        $invoice->status = 'paid';
        $invoice->paid_at = Carbon::now();
        $invoice->save();

        return $invoice->fresh();
    }

    public static function cancel(Invoice $invoice): Invoice
    {
        $invoice->status = 'cancelled';
        $invoice->save();

        return $invoice->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public static function payload(Invoice $invoice, InvoiceSetting $settings): array
    {
        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'kind' => $invoice->kind,
            'status' => $invoice->status,
            'client_name' => $invoice->client_name,
            'client_address' => $invoice->client_address,
            'client_email' => $invoice->client_email,
            'client_siret' => $invoice->client_siret,
            'issue_date' => $invoice->issue_date?->format('Y-m-d'),
            'due_date' => $invoice->due_date?->format('Y-m-d'),
            'paid_at' => $invoice->paid_at?->format('Y-m-d'),
            'lines' => collect($invoice->lines ?? [])->map(fn ($line) => [
                'description' => $line['description'] ?? '',
                'qty' => $line['qty'] ?? 1,
                'unit_price' => ($line['unit_price'] ?? 0) / 100,
                'vat' => ($line['vat'] ?? 0) / 100,
                'ht' => Money::format((int) ($line['ht'] ?? 0)),
                'tva' => Money::format((int) ($line['tva'] ?? 0)),
                'ttc' => Money::format((int) ($line['ttc'] ?? 0)),
                'subtotal' => Money::format((int) ($line['ht'] ?? 0)),
                'tax' => Money::format((int) ($line['tva'] ?? 0)),
                'total' => Money::format((int) ($line['ttc'] ?? 0)),
            ])->all(),
            'total_ht' => $invoice->cents('total_ht'),
            'total_tva' => $invoice->cents('total_tva'),
            'total_ttc' => $invoice->cents('total_ttc'),
            'subtotal' => $invoice->cents('total_ht'),
            'tax' => $invoice->cents('total_tva'),
            'total' => $invoice->cents('total_ttc'),
            'notes' => $invoice->notes,
            'print_url' => route('app.invoices.print', $invoice),
            'franchise_tva' => (bool) $settings->franchise_tva,
            'vat_rate' => $settings->vatRatePercent(),
        ];
    }
}
