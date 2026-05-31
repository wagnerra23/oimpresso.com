<?php

use Inertia\Inertia;
use Modules\ComunicacaoVisual\Entities\Material;
use Modules\ComunicacaoVisual\Http\Controllers\ApontamentoController;
use Modules\ComunicacaoVisual\Http\Controllers\InstallController;
use Modules\ComunicacaoVisual\Http\Controllers\OrcamentoController;

// Hub + Calculadora de m² (Wagner 2026-05-31): rota raiz renderiza Index.tsx
// que entrega VALOR REAL na v1 — a calculadora de orçamento por m² (US-COMVIS-001),
// ligada ao endpoint authoritative POST /comunicacao-visual/api/calcular.
// Antes era stub "em construção" de 4 cards (sem AppShellV2, paleta crua, sem
// funcionalidade) — board SCREEN-GRADE 2026-05-30 nota 54.
// Substitui o dropdown legacy do DataController que apontava pra URLs
// /comunicacao-visual/admin/* não existentes (404).
// As demais áreas (OS/PCP, Materiais, Apontamentos) seguem como "em breve"
// honesto — APIs Sprint 1 ativas em /comunicacao-visual/api/*.
Route::middleware(['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->prefix('comunicacao-visual')
    ->group(function () {
        Route::get('/', function () {
            // Permission gate canon — qualquer perm de visualização CV libera o hub.
            if (! auth()->user()->can('superadmin')
                && ! auth()->user()->can('comvis.orcamento.view')
                && ! auth()->user()->can('comvis.os.view')) {
                abort(403, 'Sem permissão Comunicação Visual (comvis.orcamento.view ou comvis.os.view).');
            }

            // Catálogo de materiais ativos do business (multi-tenant Tier 0:
            // Material aplica global scope business_id automaticamente — ADR 0093).
            // Alimenta o seletor da calculadora pra Larissa escolher "Lona Front"
            // em vez de digitar preço/m² na mão. Só campos necessários no front.
            $materiais = Material::query()
                ->where('ativo', true)
                ->orderBy('nome')
                ->get(['id', 'nome', 'categoria', 'unidade', 'preco_venda_m2'])
                ->map(fn (Material $m) => [
                    'id'             => $m->id,
                    'nome'           => $m->nome,
                    'categoria'      => $m->categoria,
                    'unidade'        => $m->unidade,
                    'preco_venda_m2' => (float) $m->preco_venda_m2,
                ])
                ->values();

            return Inertia::render('ComunicacaoVisual/Index', [
                'bizName'    => session('business.name', 'oimpresso'),
                'materiais'  => $materiais,
                'podeCriar'  => auth()->user()->can('superadmin')
                    || auth()->user()->can('comvis.orcamento.create'),
            ]);
        })->name('comunicacao-visual.index');
    });

// Rotas Install 1-click (ADR 0024 / BaseModuleInstallController).
// Sem essas rotas o action() helper em Install/ModulesController vira '#'
// e o botão "Install" da tela /manage-modules fica sem ação.
// Incidente documentado: ConsultaOs 2026-05-04 (RUNBOOK §troubleshooting).
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('comunicacao-visual')
    ->group(function () {
        Route::get('install',           [InstallController::class, 'index']);
        Route::get('install/uninstall', [InstallController::class, 'uninstall']);
        Route::get('install/update',    [InstallController::class, 'update']);
    });

// Sprint 1 — US-COMVIS-001: API de cálculo m² + persistência de orçamentos.
// Middleware padrão UltimatePOS pra rotas admin com autenticação completa.
// Wave 10 D8 Security: throttle:60,1 — proteção contra abuso de cálculo m² authoritative
// (endpoint stateless mas faz DB-write em /orcamentos e /apontamentos).
Route::middleware(['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin', 'throttle:60,1'])
    ->prefix('comunicacao-visual/api')
    ->group(function () {
        // Preview authoritative server-side (sem persistência)
        Route::post('calcular', [OrcamentoController::class, 'calcular'])
            ->name('comvis.api.calcular');

        // Persistência de orçamentos
        Route::post('orcamentos', [OrcamentoController::class, 'store'])
            ->name('comvis.api.orcamentos.store');

        // Consulta de orçamento por ID (multi-tenant: global scope filtra automaticamente)
        Route::get('orcamentos/{id}', [OrcamentoController::class, 'show'])
            ->name('comvis.api.orcamentos.show');

        // Sprint 1 — US-COMVIS-004: Spool Plotter / Apontamento de Produção
        // ATENÇÃO: em-andamento registrado antes de {apontamento} pra evitar conflito de rota
        Route::get('apontamentos/em-andamento', [ApontamentoController::class, 'emAndamento'])
            ->name('comvis.api.apontamentos.em_andamento');

        Route::get('apontamentos', [ApontamentoController::class, 'index'])
            ->name('comvis.api.apontamentos.index');

        Route::post('apontamentos/iniciar', [ApontamentoController::class, 'iniciar'])
            ->name('comvis.api.apontamentos.iniciar');

        Route::post('apontamentos/{apontamento}/finalizar', [ApontamentoController::class, 'finalizar'])
            ->name('comvis.api.apontamentos.finalizar');

        Route::post('apontamentos/{apontamento}/cancelar', [ApontamentoController::class, 'cancelar'])
            ->name('comvis.api.apontamentos.cancelar');
    });
