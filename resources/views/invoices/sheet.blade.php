@php
    $template = $settings->resolvedTemplate();
    $title = $settings->documentTitle($invoice);
    $logo = $invoice->workspace?->logoUrl();
    $accent = $template['accent'];
@endphp
<article class="invoice-sheet" style="--invoice-accent: {{ $accent }}">
    <div class="invoice-sheet__bar"></div>
    <header class="invoice-sheet__head">
        <div>
            @if ($template['show_logo'] && $logo)
                <img class="invoice-sheet__logo" src="{{ $logo }}" alt="">
            @endif
            <h1>{{ $title }}</h1>
            <p class="invoice-sheet__number">{{ $invoice->number ?: 'Brouillon' }}</p>
            <p>
                Date : {{ $invoice->issue_date?->format('d/m/Y') ?: '—' }}
                @if ($template['show_due_date'])
                    · Échéance : {{ $invoice->due_date?->format('d/m/Y') ?: '—' }}
                @endif
            </p>
        </div>
        <div class="invoice-sheet__seller">
            <strong>{{ $settings->legal_name }}</strong>
            <p>{!! nl2br(e($settings->address)) !!}</p>
            @if ($settings->siret)<p>SIRET {{ $settings->siret }}</p>@endif
            @if ($settings->ape)<p>APE {{ $settings->ape }}</p>@endif
            @if ($settings->tva_number)<p>TVA {{ $settings->tva_number }}</p>@endif
        </div>
    </header>

    @if ($template['intro'] !== '')
        <p class="invoice-sheet__intro">{{ $template['intro'] }}</p>
    @endif

    <section class="invoice-sheet__client">
        <h2>Client</h2>
        <div>
            <strong>{{ $invoice->client_name }}</strong>
            <p>{!! nl2br(e($invoice->client_address)) !!}</p>
            @if ($invoice->client_siret)<p>SIRET {{ $invoice->client_siret }}</p>@endif
            @if ($invoice->client_email)<p>{{ $invoice->client_email }}</p>@endif
        </div>
    </section>

    <table class="invoice-sheet__lines">
        <thead>
            <tr>
                <th>Désignation</th>
                <th class="num">Qté</th>
                <th class="num">PU HT</th>
                <th class="num">TVA</th>
                <th class="num">Sous-total</th>
                <th class="num">Taxe</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoice->lines ?? [] as $line)
                <tr>
                    <td>{{ $line['description'] ?? '' }}</td>
                    <td class="num">{{ number_format((float) ($line['qty'] ?? 0), 2, ',', ' ') }}</td>
                    <td class="num">{{ \App\Support\Erp\Money::format((int) ($line['unit_price'] ?? 0)) }}</td>
                    <td class="num">{{ \App\Support\Erp\Money::vatLabel((int) ($line['vat'] ?? 0)) }}</td>
                    <td class="num">{{ \App\Support\Erp\Money::format((int) ($line['ht'] ?? 0)) }}</td>
                    <td class="num">{{ \App\Support\Erp\Money::format((int) ($line['tva'] ?? 0)) }}</td>
                    <td class="num">{{ \App\Support\Erp\Money::format((int) ($line['ttc'] ?? 0)) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">Aucune ligne</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <aside class="invoice-sheet__totals">
        <p><span>Sous-total HT</span> <strong>{{ $invoice->cents('total_ht') }}</strong></p>
        <p><span>Taxe (TVA {{ \App\Support\Erp\Money::vatLabel($settings->vatRateBps()) }})</span> <strong>{{ $invoice->cents('total_tva') }}</strong></p>
        <p class="invoice-sheet__ttc"><span>Total TTC</span> <strong>{{ $invoice->cents('total_ttc') }}</strong></p>
    </aside>

    @if ($invoice->notes)
        <p class="invoice-sheet__notes">{{ $invoice->notes }}</p>
    @endif

    @if ($template['footer'] !== '')
        <p class="invoice-sheet__footer">{{ $template['footer'] }}</p>
    @endif

    <footer class="invoice-sheet__legal">
        @if ($settings->franchise_tva)
            <p>TVA non applicable, art. 293 B du CGI.</p>
        @endif
        @if ($template['legal'] !== '')
            <p>{{ $template['legal'] }}</p>
        @endif
        <p>Pas d’escompte pour paiement anticipé. En cas de retard de paiement, pénalités au taux de {{ $settings->late_penalty ?: '3 fois le taux d’intérêt légal' }} et indemnité forfaitaire de recouvrement de 40 € (art. L441-10 du code de commerce).</p>
        <p>Document {{ $invoice->status === 'draft' ? 'non émis' : 'émis' }} depuis l’espace {{ $invoice->workspace->name }}.</p>
    </footer>
</article>
