<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $invoice->number ?: 'Brouillon' }} — {{ $settings->legal_name }}</title>
    <link rel="stylesheet" href="{{ asset('css/invoice-print.css') }}?v=erp-2">
</head>
<body class="invoice-print-body">
    <div class="invoice-toolbar">
        <div>
            <strong>{{ $invoice->number ?: 'Brouillon' }}</strong>
            <p>Print this invoice, or choose <em>Save as PDF</em> in the browser dialog. Nothing is sent to a popup.</p>
        </div>
        <button type="button" class="invoice-toolbar__print" id="invoice-print-btn">Print / Save as PDF</button>
    </div>
    @include('invoices.sheet')
    <script>
        document.getElementById("invoice-print-btn")?.addEventListener("click", () => {
            window.focus();
            window.print();
        });
    </script>
</body>
</html>
