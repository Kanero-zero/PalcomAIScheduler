<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.'SCHEDORA AI' : 'SCHEDORA AI' }}
</title>

<link rel="icon" type="image/png" href="{{ asset('schedora/schedora-logo.png') }}">
<link rel="apple-touch-icon" href="{{ asset('schedora/schedora-logo.png') }}">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
