<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Baserow')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/baserow.css') }}?v=erp-2">
    <link rel="icon" href="{{ asset('images/favicon.svg') }}">
</head>
<body class="{{ $bodyClass ?? '' }}">
    @yield('content')
    <div id="toast-root" class="toast-root" aria-live="polite"></div>
    <div id="modal-root"></div>
    <div id="menu-root"></div>
    <script src="{{ asset('js/app.js') }}?v=erp-2"></script>
    @stack('scripts')
</body>
</html>
