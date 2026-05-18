<?php

declare(strict_types=1);

/**
 * Config canônica do módulo Financeiro (raiz `config/financeiro.php`).
 *
 * Por que raiz (e não `Modules/Financeiro/Config/`)?
 *   - `Modules/Financeiro/Config/` (config.php, retention.php) cuida de
 *     concerns INTERNOS do módulo (nome/cor/sidebar, retention LGPD).
 *   - `config/financeiro.php` cuida de INTEGRAÇÕES EXTERNAS expostas via
 *     `config('financeiro.*')`, padrão Laravel — Pluggy é o primeiro consumer.
 *
 * Tier 0 IRREVOGÁVEL (ADR 0093 + ADR 0094):
 *   - `enabled => false` default (Wagner habilita por business no .env)
 *   - Credentials NUNCA no git — só env var
 *   - Webhook signature HMAC obrigatória pra aceitar callback
 *
 * @see Modules\Financeiro\Services\Integrations\PluggyClient
 * @see Modules\Financeiro\Services\Integrations\PluggyBankSyncService
 * @see W27 estado-da-arte 2026-05-17 — Pluggy padrão Open Banking BR
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Mock Cowork Mode — Wagner regra 2026-05-18
    |--------------------------------------------------------------------------
    |
    | "coloque em produção ele por favor. na integra todo financeiro"
    |
    | Quando true, todas as rotas GET /financeiro/* retornam o HTML mock
    | canon do bundle Cowork (public/cowork-preview/<tela>.html) literal,
    | sem layout Laravel + sem Inertia. Sidebar oimpresso some no
    | Financeiro (mock tem sidebar próprio canon Cowork).
    |
    | Reversível: setar FINANCEIRO_MOCK_COWORK=false no .env (ou commit
    | mudança aqui pra false) quando quiser voltar pra render Inertia
    | normal (Pages/Financeiro/Unificado/Index.tsx etc).
    |
    | Tier 0 multi-tenant:
    |   - middleware auth + Spatie permissions continuam no __construct
    |     dos controllers — só usuário logado com permission acessa
    |   - Mock NÃO tem business_id real (mostra dados ROTA LIVRE template)
    |   - POST de baixa/edit continuam funcionando nas rotas reais
    |     (só GETs renderizam mock)
    |
    | Cherry-pick falhou 4× (PR #1085 → #1091 → #1092 → #1094 → #1095).
    | Adaptação Inertia errou layout o dia inteiro 2026-05-18.
    | Solução: servir mock canon literal em prod, autorizar mudanças DEPOIS
    | tela-por-tela.
    */
    'mock_cowork_mode' => (bool) env('FINANCEIRO_MOCK_COWORK', true),

    /*
    | Sidebar wrap (Onda #4b — sidebar REAL respeitando 3 camadas via
    | ShellMenuBuilder + AdminSidebarMenu canon). Wagner ativou 2026-05-18.
    |
    | Default TRUE em prod desde Onda #4b (PR #1113). Bridge JS fetcha
    | /financeiro/cowork-sidebar-data e renderiza sidebar oimpresso filtrado
    | por business + user + Spatie permissions.
    |
    | Kill-switch IRREVOGÁVEL pra reverter:
    |   1. FINANCEIRO_SIDEBAR_WRAP=false no .env Hostinger
    |   2. localStorage.setItem('__OIMPRESSO_SIDEBAR_OFF__', '1') runtime
    */
    'sidebar_wrap_enabled' => (bool) env('FINANCEIRO_SIDEBAR_WRAP', true),

    /*
    | Mapping rota.name → arquivo HTML mock canon servido de
    | public/cowork-preview/.
    |
    | Quando rota não está mapeada, fallback é "Financeiro Unificado.html"
    | (mock principal que contém sub-rotas via routing client-side React).
    */
    /*
    | Cada rota mapeia pra:
    |   - html: arquivo em public/cowork-preview/ (Oimpresso ERP - Chat.html é
    |     o SPA shell completo com app.jsx boot que monta TODOS módulos)
    |   - cowork_route: valor que o trait injeta em localStorage["oimpresso.route"]
    |     ANTES do app.jsx rodar, pra abrir a tela correta direto. Routes válidas
    |     no app.jsx Cowork: financeiro / fin-fluxo / fin-dre / boletos
    |
    | Usar `Oimpresso ERP - Chat.html` (não Financeiro Unificado.html standalone)
    | porque o standalone NÃO tem ReactDOM.createRoot() — só o Chat shell tem app.jsx.
    */
    'mock_route_map' => [
        'financeiro.unificado.index'        => ['html' => 'Oimpresso ERP - Chat.html', 'cowork_route' => 'financeiro'],
        'financeiro.fluxo.index'            => ['html' => 'Oimpresso ERP - Chat.html', 'cowork_route' => 'fin-fluxo'],
        'financeiro.dashboard'              => ['html' => 'Oimpresso ERP - Chat.html', 'cowork_route' => 'financeiro'],
        'financeiro.extrato.index'          => ['html' => 'Oimpresso ERP - Chat.html', 'cowork_route' => 'financeiro'],
        'financeiro.relatorios.index'       => ['html' => 'Oimpresso ERP - Chat.html', 'cowork_route' => 'fin-dre'],
        'financeiro.plano-contas.index'     => ['html' => 'Oimpresso ERP - Chat.html', 'cowork_route' => 'financeiro'],
        'financeiro.contas-receber.index'   => ['html' => 'Oimpresso ERP - Chat.html', 'cowork_route' => 'financeiro'],
        'financeiro.contas-pagar.index'     => ['html' => 'Oimpresso ERP - Chat.html', 'cowork_route' => 'financeiro'],
        'financeiro.contas-bancarias.index' => ['html' => 'Oimpresso ERP - Chat.html', 'cowork_route' => 'financeiro'],
        'financeiro.assinaturas.index'      => ['html' => 'Oimpresso ERP - Chat.html', 'cowork_route' => 'financeiro'],
        'financeiro.categorias.index'       => ['html' => 'Oimpresso ERP - Chat.html', 'cowork_route' => 'financeiro'],
        'financeiro.boletos.index'          => ['html' => 'Oimpresso ERP - Chat.html', 'cowork_route' => 'boletos'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pluggy — Open Banking Brasil (estado-da-arte W27)
    |--------------------------------------------------------------------------
    |
    | Pluggy.ai é a plataforma B2B padrão de Open Finance no BR (Itaú,
    | Bradesco, BB, Nubank, C6, Inter via connectors regulados + scraping).
    |
    | Fluxo:
    |   1. Backend autentica (POST /auth com CLIENT_ID + CLIENT_SECRET) → API key 2h
    |   2. Backend cria connect_token (POST /connect_token) — limited 30min
    |   3. Frontend mostra Pluggy Connect widget com token → user conecta banco
    |   4. Pluggy retorna `item_id` (representa conexão user-instituição)
    |   5. Backend lista accounts/transactions e importa pra ExtratoLancamento
    |   6. Webhook (POST /webhooks/pluggy) avisa de syncs delta
    |
    | Custo prod estimado: ~R$ 0,30-1,00/conta/mês (varia connector).
    */
    'pluggy' => [
        'enabled'         => env('PLUGGY_ENABLED', false),
        'client_id'       => env('PLUGGY_CLIENT_ID'),
        'client_secret'   => env('PLUGGY_CLIENT_SECRET'),
        'base_url'        => env('PLUGGY_BASE_URL', 'https://api.pluggy.ai'),
        'webhook_secret'  => env('PLUGGY_WEBHOOK_SECRET'),

        // HTTP timeouts (segundos). Pluggy SLA p95 < 2s pra leitura.
        'timeout'         => (int) env('PLUGGY_TIMEOUT', 15),
        'retry_times'     => (int) env('PLUGGY_RETRY_TIMES', 2),
        'retry_sleep_ms'  => (int) env('PLUGGY_RETRY_SLEEP_MS', 250),

        // Cache TTL da API key (Pluggy expira em 2h; mantemos 100min de folga).
        'api_key_cache_ttl_sec' => (int) env('PLUGGY_API_KEY_CACHE_TTL_SEC', 6000),

        // Sync incremental — janela default em dias pra puxar transactions.
        'sync_window_days' => (int) env('PLUGGY_SYNC_WINDOW_DAYS', 30),

        // Mock mode pra Pest local sem chamar Pluggy real (ZERO custo).
        'force_mock'       => env('PLUGGY_FORCE_MOCK', false),
    ],

];
