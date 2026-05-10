<?php

/**
 * DRAFT — Routes/web.php Modules/ComunicacaoVisual.
 *
 * Imitar Modules/ADS/Routes/web.php + Modules/ConsultaOs/Routes/web.php (validados 2026-05-03/04).
 *
 * ⚠️ AS 3 ROTAS INSTALL ABAIXO SAO OBRIGATORIAS (RUNBOOK-criar-modulo §3).
 * Sem elas, app/Http/Controllers/Install/ModulesController.php cai no catch e
 * `install_link` vira '#' — botão Install fica visível mas SEM AÇÃO.
 *
 * Felipe: o resto das rotas (orcamento, OS, materiais, etc) entram conforme US-COMVIS-NNN
 * forem implementadas em PRs separados. Este draft cobre APENAS o esqueleto necessario
 * pra o módulo aparecer instalavel em /manage-modules.
 *
 * Padrao Route::has() pra link publico condicional (passo 7 do RUNBOOK):
 *   - Quando US-COMVIS-010 (Provador online publico) for entregue, registrar nome
 *     'comvis.orcamento.publico' aqui — HandleInertiaRequests::share() le 'publicRoutes'
 *     e SiteHeader.tsx usa pra mostrar/esconder link "Fazer orcamento" do CMS publico.
 */

use Illuminate\Support\Facades\Route;
use Modules\ComunicacaoVisual\Http\Controllers\InstallController;

// ─── Rotas Install 1-clique (ADR 0024) ──────────────────────────────────
// Sem essas 3 rotas o botao Install no /manage-modules vira href="#".
Route::middleware(['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('comvis')
    ->group(function () {
        Route::get('install',           [InstallController::class, 'index']);
        Route::get('install/uninstall', [InstallController::class, 'uninstall']);
        Route::get('install/update',    [InstallController::class, 'update']);
    });

// ─── Rotas admin do modulo (placeholders — Felipe preenche conforme US) ──
// Stack canonica UltimatePOS (CLAUDE.md §"Sempre fazer"):
Route::group([
    'middleware' => ['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'],
    'prefix'     => 'comvis',
], function () {
    // PLACEHOLDER — descomente conforme US for entregue:
    //
    // US-COMVIS-001 (Calculo automatico m2):
    // Route::post('/orcamento/calcular', [OrcamentoController::class, 'calcular'])
    //     ->name('comvis.orcamento.calcular');
    //
    // US-COMVIS-002 (CRUD material):
    // Route::resource('/materiais', MaterialController::class)
    //     ->names('comvis.materiais');
    //
    // US-COMVIS-003 (Kanban PCP):
    // Route::get('/os/{id}', [OsController::class, 'show'])
    //     ->whereNumber('id')->name('comvis.os.show');
    //
    // ATENCAO Felipe: Ziggy NAO esta disponivel neste Inertia (RUNBOOK pegadinhas).
    // Em Pages React usar strings literais: href={`/comvis/os/${id}`} — NAO route('comvis.os.show', id).
});

// ─── Rota publica (US-COMVIS-010 — adiar pra P2, NAO entregar Sprint 1) ──
// Quando entregar, registrar com name pra Route::has() no header CMS.
//
// Route::get('/b/{slug}/orcamento', [OrcamentoPublicoController::class, 'show'])
//     ->name('comvis.orcamento.publico')
//     ->middleware(['web']);
