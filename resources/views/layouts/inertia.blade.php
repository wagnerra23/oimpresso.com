@inject('request', 'Illuminate\Http\Request')
@php
    /*
     * Tema: resolvido server-side por usuário logado (coluna users.ui_theme).
     * - 'dark'|'light' = override explícito → renderiza html class='dark' ou vazio
     * - null  = "seguir sistema" → script inline abaixo decide via prefers-color-scheme
     *
     * Anon (tela de login): sem user, só localStorage/system preference.
     */
    $userTheme = auth()->check() ? auth()->user()->ui_theme : null;
    $htmlClass = $userTheme === 'dark' ? 'dark' : '';
    $autoMode = $userTheme === null;
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="{{ $htmlClass }}" data-theme="{{ $userTheme ?? 'auto' }}">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <title inertia>{{ config('app.name', 'OI Impresso') }}</title>

    {{-- Anti-flash dark mode. Rodamos ANTES do <body> pintar: se modo=auto, decide
         pela preferência do sistema; senão já veio correto do servidor.
         Sem este script, auto-mode pisca branco → escuro (vira estilo amador). --}}
    <script>
        (function () {
            try {
                var el = document.documentElement;
                var mode = el.getAttribute('data-theme');
                if (mode === 'auto' || !mode) {
                    // fallback localStorage só p/ anon (login page); depois de logar, server manda
                    var stored = localStorage.getItem('oi.theme');
                    var dark = stored === 'dark' ||
                               (stored !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                    if (dark) el.classList.add('dark');
                }
            } catch (e) { /* storage indisponível, segue light */ }
        })();
    </script>

    {{-- Inertia React + Tailwind 4 (pipeline separado do AdminLTE) --}}
    @viteReactRefresh
    @vite(['resources/css/inertia.css', 'resources/js/app.tsx'], 'build-inertia')

    @inertiaHead
</head>
<body class="bg-background text-foreground antialiased">
    @inertia
</body>
</html>
