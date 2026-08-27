<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0f4c5c">
        <link rel="manifest" href="{{ asset('build/manifest.webmanifest') }}">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
        <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('pwa-192x192.png') }}">
        <title inertia>{{ config('app.name', 'Hotel ERP') }}</title>
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
        @if (file_exists(public_path('build/registerSW.js')))
            <script src="{{ asset('build/registerSW.js') }}" defer></script>
        @endif
        @inertiaHead
    </head>
    <body class="antialiased">
        @inertia
    </body>
</html>
