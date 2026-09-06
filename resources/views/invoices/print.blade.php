<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number ?: 'Brouillon' }} — {{ $settings->legal_name }}</title>
    <link rel="stylesheet" href="{{ asset('css/baserow.css') }}?v=erp-1">
</head>
<body class="invoice-print-body">
<article class="invoice-sheet">
    <header class="invoice-sheet__head">
        <div>
            <h1>{{ $invoice->kind === 'avoir' ? 'Avoir' : ($invoice->kind === 'devis' ? 'Devis' : 'Facture') }}</h1>
            <p class="invoice-sheet__number">{{ $invoice->number ?: 'Brouillon' }}</p>
            <p>Date : {{ $invoice->issue_date?->format('d/m/Y') ?: '—' }} · Échéance : {{ $invoice->due_date?->format('d/m/Y') ?: '—' }}</p>
        </div>
        <div class="invoice-sheet__seller">
            <strong>{{ $settings->legal_name }}</strong>
            <p>{!! nl2br(e($settings->address)) !!}</p>
            @if ($settings->siret)<p>SIRET {{ $settings->siret }}</p>@endif
            @if ($settings->ape)<p>APE {{ $settings->ape }}</p>@endif
            @if ($settings->tva_number)<p>TVA {{ $settings->tva_number }}</p>@endif
        </div>
    </header>

    <section class="invoice-sheet__client">
        <h2>Client</h2>
        <strong>{{ $invoice->client_name }}</strong>
        <p>{!! nl2br(e($invoice->client_address)) !!}</p>
        @if ($invoice->client_siret)<p>SIRET {{ $invoice->client_siret }}</p>@endif
        @if ($invoice->client_email)<p>{{ $invoice->client_email }}</p>@endif
    </section>

    <table class="invoice-sheet__lines">
        <thead>
            <tr>
                <th>Désignation</th>
                <th>Qté</th>
                <th>PU HT</th>
                <th>TVA</th>
                <th>Total HT</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->lines ?? [] as $line)
                <tr>
                    <td>{{ $line['description'] ?? '' }}</td>
                    <td>{{ number_format((float) ($line['qty'] ?? 0), 2, ',', ' ') }}</td>
                    <td>{{ \App\Support\Erp\Money::format((int) ($line['unit_price'] ?? 0)) }}</td>
                    <td>{{ \App\Support\Erp\Money::vatLabel((int) ($line['vat'] ?? 0)) }}</td>
                    <td>{{ \App\Support\Erp\Money::format((int) ($line['ht'] ?? 0)) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <aside class="invoice-sheet__totals">
        <p>Total HT <strong>{{ $invoice->cents('total_ht') }}</strong></p>
        <p>TVA <strong>{{ $invoice->cents('total_tva') }}</strong></p>
        <p class="invoice-sheet__ttc">Total TTC <strong>{{ $invoice->cents('total_ttc') }}</strong></p>
    </aside>

    @if ($invoice->notes)
        <p class="invoice-sheet__notes">{{ $invoice->notes }}</p>
    @endif

    <footer class="invoice-sheet__legal">
        @if ($settings->franchise_tva)
            <p>TVA non applicable, art. 293 B du CGI.</p>
        @endif
        <p>Pas d’escompte pour paiement anticipé. En cas de retard de paiement, pénalités au taux de {{ $settings->late_penalty ?: '3 fois le taux d’intérêt légal' }} et indemnité forfaitaire de recouvrement de 40 € (art. L441-10 du code de commerce).</p>
        <p>Document {{ $invoice->status === 'draft' ? 'non émis' : 'émis' }} depuis l’espace {{ $invoice->workspace->name }}.</p>
    </footer>
</article>
</body>
</html>
