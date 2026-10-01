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

    /*
     * VISREG — relógio do NAVEGADOR congelado (gate visual-regression).
     *
     * As suítes de baseline congelam o relógio do PHP com Carbon::setTestNow();
     * telas que chamam `new Date()` no browser (JanaAreaHeader.tsx:80,
     * JanaCockpit.tsx:356 e :107) ficavam de fora e faziam a baseline drifar POR
     * MINUTO. Aqui a outra ponta é fechada: o shim entra no <head> antes do
     * bundle da app, então `new Date()`/`Date.now()` já nascem congelados.
     *
     * Instante = Carbon::parse() do MESMO valor passado ao setTestNow, logo os
     * dois relógios contam a mesma história.
     *
     * GUARDA dupla (igual VisregStateMiddleware): nunca em produção + só com a
     * env VISREG_FREEZE_CLOCK, que só existe no .env do job visual-regression.
     * Sem ela, nada é renderizado e o custo é uma comparação de string.
     */
    $visregFreezeAt = null;
    $visregFreezeShim = null;
    $visregFreezeRaw = config('visreg.freeze_clock');

    if (! app()->isProduction() && is_string($visregFreezeRaw) && $visregFreezeRaw !== '') {
        $visregShimPath = resource_path('visreg/freeze-clock.js');

        if (is_file($visregShimPath)) {
            // ->timestamp (segundos) * 1000: o instante do gate não tem fração, e
            // isso não depende de getTimestampMs() estar disponível na versão do Carbon.
            $visregFreezeAt = \Illuminate\Support\Carbon::parse($visregFreezeRaw)->timestamp * 1000;
            $visregFreezeShim = file_get_contents($visregShimPath);
        }
    }
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="{{ $htmlClass }}" data-theme="{{ $userTheme ?? 'auto' }}">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <title data-inertia>{{ config('app.name', 'OI Impresso') }}</title>

    {{-- VISREG — congela `new Date()`/`Date.now()` do navegador. PRIMEIRO script do
         <head> de propósito: qualquer coisa que leia o relógio antes dele leria o
         relógio vivo. Fonte única em `resources/visreg/freeze-clock.js` (lida, não
         copiada — o bite-test carrega o MESMO arquivo). No-op sem a env
         VISREG_FREEZE_CLOCK e sempre no-op em produção — ver o @php acima. --}}
    @if ($visregFreezeShim !== null)
        <script>window.__VISREG_FREEZE_AT__ = {{ $visregFreezeAt }};{!! $visregFreezeShim !!}</script>
    @endif

    {{-- IBM Plex Sans/Mono — SELF-HOSTED desde 2026-07-16 (ITEM 7 · 3c). O <link> do
         Google Fonts (`display=swap`) saiu daqui: os @font-face agora vêm de
         `@fontsource/ibm-plex-sans|mono`, importados em resources/js/app.tsx e
         servidos pelo nosso domínio via manifest do `build-inertia`.

         Por que mudou: o `display=swap` + CDN tornavam a fonte não-determinística no
         runner do gate visual, que compensava injetando `* { font-family: Arial
         !important }` — e esse force cegava o gate pra regressão de font-family. Com
         @font-face local + `document.fonts.ready`, a fonte real carrega determinística
         e o force pôde sair. Instalar a fonte no ubuntu-24.04 não resolveria: o
         @font-face do CDN vence o SO.

         O import é JS (não `@import` no CSS) pelo motivo que este comentário já
         registrava: `@import` dentro de CSS bundleado era descartado pelo Vite no build
         de produção. Nada de preconnect: não há mais origem externa a aquecer.

         NOTA: `layouts/home.blade.php` (bundle público, fora do Inertia) ainda carrega
         o CDN — fora do escopo do gate visual, que só exercita telas Inertia. --}}

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
