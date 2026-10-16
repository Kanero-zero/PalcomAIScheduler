<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>Masuk — SCHEDORA AI</title>
    <meta name="theme-color" content="#0A3D91">
    <link rel="icon" type="image/png" href="{{ asset('schedora/schedora-logo.png') }}">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
    <link rel="stylesheet" href="{{ asset('schedora/login-v2.css') }}">
    <script src="{{ asset('schedora/login-v2.js') }}" defer></script>
</head>
<body class="schedora-login antialiased">
    {{ $slot }}

    @persist('toast')
        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>
    @endpersist

    @fluxScripts
</body>
</html>
