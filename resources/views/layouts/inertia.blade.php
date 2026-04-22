@inject('request', 'Illuminate\Http\Request')
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <title inertia>{{ config('app.name', 'OI Impresso') }}</title>

    {{-- Inertia React + Tailwind 4 (pipeline separado do AdminLTE) --}}
    @viteReactRefresh
    @vite(['resources/css/inertia.css', 'resources/js/app.tsx'], 'build-inertia')

    @inertiaHead
</head>
<body class="bg-background text-foreground antialiased">
    @inertia
</body>
</html>
