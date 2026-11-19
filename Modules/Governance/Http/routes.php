<?php

use Illuminate\Support\Facades\Route;
use Modules\Governance\Http\Controllers\DashboardController;
use Modules\Governance\Http\Controllers\InstallController;
use Modules\Governance\Http\Controllers\DataController;
use Modules\Governance\Http\Controllers\PoliciesController;
use Modules\Governance\Http\Controllers\AuditController;
use Modules\Governance\Http\Controllers\DriftAlertsController;
use Modules\Governance\Http\Controllers\DsRolloutController;
use Modules\Governance\Http\Controllers\CustosController;
use Modules\Governance\Http\Controllers\QualidadeIaController;

/*
|--------------------------------------------------------------------------
| Module Governance — Web Routes
|--------------------------------------------------------------------------
|
| ADR 0086 MVP — UI básica /governance lista pendências consolidadas.
| Rotas detalhadas (policies CRUD, audit filtrável, drift alerts) ficam
| pra próxima fase quando frontend for implementado.
|
*/

// ── Gate `can:` por rota (2026-09-08, ADR 0392 §D-D passo 2) ────────────────
// Cada tela exige a MESMA permission que o `DataController::modifyAdminMenu` já
// usa pra publicar o item de menu — o gate de sidebar e o gate de URL deixam de
// divergir (recibo da 0392: "gate de sidebar não é gate de URL"). Nenhuma
// permission NOVA foi criada: as três vêm de `DataController::user_permissions()`.
// `/governance/dashboard` entrou em 2026-11-19 (lote L0 da auditoria do
// Governance, aprovado pelo [W]): até então era só `auth`, e qualquer usuário de
// qualquer empresa via o custo de IA somado de todas as empresas.
//
// ⚠️ O QUE ISTO NÃO FECHA — estado em 2026-11-19, conferido na ADR 0415:
// `AuthServiceProvider` registra `Gate::before`, que devolve `true` para o papel
// `Admin#{business_id}` em toda permission fora das três reservadas e da lista
// `permissoesDePlataforma()`. A 0415 (aceita 2026-09-24) tirou de lá as
// permissões de plataforma (`admin_only` do catálogo MCP + `jana.superadmin`),
// mas `governance.*` NÃO está nessa lista. Logo `can:` barra o usuário sem a
// permission e NÃO barra o dono de uma empresa. O passo 1 da 0392 (concessão
// dos scopes `admin_only`) foi fechado pelo #6962; tratar `governance.*` como
// plataforma é o passo 3 da 0392, decisão [W] em aberto.

Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->prefix('governance')
    ->name('governance.')
    ->group(function () {
        // Wagner 2026-05-22: /governance redireciona pra /ia (entry-point
        // canon do oimpresso é o hub IA/Jana — sidebar v3 ADR 0180).
        // Dashboard governance original preservado em /governance/dashboard.
        Route::get('/', fn () => redirect('/ia', 302))->name('admin.dashboard');
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->middleware(['throttle:60,1', 'can:governance.dashboard.view'])
            ->name('admin.dashboard.legacy');

        // Policies CRUD (mcp_governance_rules) — throttle defensivo:
        // read 60/min, toggle 10/min (operação sensível afeta enforcement runtime)
        Route::get('/policies', [PoliciesController::class, 'index'])
            ->middleware(['throttle:60,1', 'can:governance.dashboard.view'])
            ->name('policies.index');
        Route::post('/policies/{id}/toggle', [PoliciesController::class, 'toggle'])
            ->middleware(['throttle:10,1', 'can:governance.policies.edit'])
            ->name('policies.toggle');

        // Audit log drill-down — throttle 30/min (query pesada DB com filtros)
        Route::get('/audit', [AuditController::class, 'index'])
            ->middleware(['throttle:30,1', 'can:governance.audit.view'])
            ->name('audit.index');

        // Drift alerts (Module Charter Art. 7) — throttle 20/min (scan filesystem caro)
        Route::get('/drift', [DriftAlertsController::class, 'index'])
            ->middleware(['throttle:20,1', 'can:governance.dashboard.view'])
            ->name('drift.index');

        // DS Rollout — plano de portar o DS em ondas + Ledger de Conformidade
        // (tradução F3 do protótipo Cowork · handoff claude.ai/design 2026-06-12).
        // Static render → throttle leve 60/min.
        Route::get('/ds-rollout', [DsRolloutController::class, 'index'])
            ->middleware(['throttle:60,1', 'can:governance.dashboard.view'])
            ->name('ds-rollout.index');

        // Custos de IA + Qualidade IA — recebidas do Modules/Jana em 2026-08-05
        // (ADR 0366 §D-B). As permissions seguem `jana.*` de propósito e vivem no
        // construtor de cada controller: `jana.admin.custos.view` no Custos e
        // `jana.mcp.usage.all` na Qualidade — são DIFERENTES entre si, e renomear
        // pra `governance.*` revogaria acesso em silêncio (ADR 0087), o que exige
        // ADR + migration própria, não carona numa mudança de dono.
        Route::get('/custos', [CustosController::class, 'index'])
            ->middleware('throttle:30,1')
            ->name('custos.index');

        Route::get('/qualidade-ia', [QualidadeIaController::class, 'index'])
            ->middleware('throttle:30,1')
            ->name('qualidade-ia.index');

        // Install hooks (ADR 0024 — pattern padronizado BaseModuleInstallController)
        Route::post('install',           [InstallController::class, 'install']);
        Route::get('install',           [InstallController::class, 'index'])
            ->name('install.index');
        Route::match(['get', 'post'], 'install/uninstall', [InstallController::class, 'uninstall'])
            ->name('install.uninstall');
        Route::match(['get', 'post'], 'install/update',    [InstallController::class, 'update'])
            ->name('install.update');
    });
