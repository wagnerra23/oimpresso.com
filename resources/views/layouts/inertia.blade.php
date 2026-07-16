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

    <title data-inertia>{{ config('app.name', 'OI Impresso') }}</title>

    {{-- IBM Plex Sans/Mono — referenciada por sells-cowork.css (e demais
         cowork bundles). Carregar via <link> aqui porque @import dentro de CSS
         bundleado é descartado pelo Vite no build de produção, e o fallback
         system-ui faz a tela parecer "errada" (issue: prod sem fonte
         enquanto localhost renderiza por IBM Plex estar instalado no SO do
         dev). --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">

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

    {{-- Ziggy: gera função `route()` global no JS a partir das rotas Laravel.
         Sem isso, todas Pages React que chamam `route('xxx.yyy')` viram
         ReferenceError silencioso (links com href=undefined). Pacote
         `tightenco/ziggy` precisa estar instalado (composer.json).

         PERF (2026-07-16): o mapa COMPLETO são 1.418 rotas / ~169 KB inline —
         99,3% do peso desta página — e o HTML é `no-cache`, então isso é
         re-baixado e re-parseado a CADA navegação, sem nunca ir pro cache.

         ANÔNIMO = páginas públicas (Pages/Site/*: Login, Home, Blogs, BlogPost,
         Page). Auditadas em 2026-07-16: ZERO chamadas a `route()` nas 4 camadas
         que elas alcançam (Pages/Site, Components/Site, SiteLayout, app.tsx) —
         navegam por <Link href> e o login faz `post('/login')` com URL literal.
         Recebem o grupo mínimo `public` (config/ziggy.php).

         LOGADO = app do ERP: mapa completo, INTOCADO. Cortar lá exigiria resolver
         as 21 chamadas `route(nomeVindoDoServidor)` (nome dinâmico via prop
         Inertia), que nenhum grep enumera — e o gate visual não pegaria a quebra
         (erro de rota acontece em clique, não em screenshot). --}}
    @auth
        @routes
    @else
        @routes('public')
    @endauth

    @inertiaHead
</head>
<body class="bg-background text-foreground antialiased">
    @inertia

    {{-- Microsoft Clarity session replay (ADR 0191) — guard server-side decide
         se renderiza. NÃO mover pro <head> (snippet oficial Microsoft é async). --}}
    @include('layouts.partials.clarity')
</body>
</html>
