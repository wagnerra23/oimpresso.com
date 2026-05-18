<?php

use Illuminate\Support\Facades\Route;
use Modules\Financeiro\Http\Controllers\AssinaturaController;
use Modules\Financeiro\Http\Controllers\BoletoController;
use Modules\Financeiro\Http\Controllers\CategoriaController;
use Modules\Financeiro\Http\Controllers\ContaBancariaController;
use Modules\Financeiro\Http\Controllers\ContaPagarController;
use Modules\Financeiro\Http\Controllers\ContaReceberController;
use Modules\Financeiro\Http\Controllers\DashboardController;
use Modules\Financeiro\Http\Controllers\ExtratoController;
use Modules\Financeiro\Http\Controllers\FluxoController;
use Modules\Financeiro\Http\Controllers\InstallController;
use Modules\Financeiro\Http\Controllers\RelatoriosController;
use Modules\Financeiro\Http\Controllers\UnificadoController;

/*
|--------------------------------------------------------------------------
| Web Routes — Financeiro
|--------------------------------------------------------------------------
| Padrão UltimatePOS (alinhado com Modules/Connector/Routes/web.php).
*/

// Install routes (acessadas via /manage-modules link "Install").
// Pattern reutilizado de Modules/Connector/Routes/web.php.
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('financeiro')
    ->group(function () {
        Route::get('install', [InstallController::class, 'index']);
        Route::get('install/uninstall', [InstallController::class, 'uninstall']);
        Route::get('install/update', [InstallController::class, 'update']);
    });

// FIN-004 — Atualizar cobranca de assinatura recorrente (HITL pending Wagner aprovou)
// Cuidado biz=4 prod ROTA LIVRE — Controller log NUNCA imprime valor real / CPF.
Route::middleware(['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('financeiro')
    ->name('financeiro.')
    ->group(function () {
        Route::get('assinaturas/atualizar', [AssinaturaController::class, 'showAtualizar'])
            ->name('assinaturas.atualizar.show');
        Route::patch('assinaturas/{assinatura}', [AssinaturaController::class, 'atualizar'])
            ->name('assinaturas.atualizar')
            ->where('assinatura', '[0-9]+');
    });

// Rotas operacionais do módulo
Route::middleware(['web', 'auth', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('financeiro')
    ->name('financeiro.')
    ->group(function () {
        // Dashboard unificado (US-FIN-013)
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Visão Unificada — Cockpit V2 (US-FIN-013/020) — protótipo Cowork 2026-05-09
        Route::get('/unificado', [UnificadoController::class, 'index'])->name('unificado.index');
        Route::get('/unificado/novo', [UnificadoController::class, 'novo'])->name('unificado.novo');
        Route::post('/unificado/{id}/baixar', [UnificadoController::class, 'baixar'])
            ->whereNumber('id')
            ->name('unificado.baixar');
        // Onda Edit 2026-05-18 — edit Sheet inline + conferido per-user DB.
        Route::put('/unificado/{id}', [UnificadoController::class, 'update'])
            ->whereNumber('id')
            ->name('unificado.update');
        Route::post('/unificado/{id}/conferir', [UnificadoController::class, 'conferir'])
            ->whereNumber('id')
            ->name('unificado.conferir');
        Route::delete('/unificado/{id}/conferir', [UnificadoController::class, 'unconferir'])
            ->whereNumber('id')
            ->name('unificado.unconferir');

        // Onda 8c 2026-05-18 — sparkline endpoint (saldo dia-a-dia últimos 30d)
        // Pure-read, JSON, Tier 0 multi-tenant via business_id global scope.
        Route::get('/unificado/saldo-sparkline', [UnificadoController::class, 'saldoSparkline'])
            ->name('unificado.saldo-sparkline');

        // Onda #4b 2026-05-18 — sidebar REAL pro Mock Cowork (3 camadas universal).
        // Bridge JS fetcha com header `X-Inertia: true` (senão middleware
        // AdminSidebarMenu pula criação do Menu).
        Route::get('/cowork-sidebar-data',
            [\Modules\Financeiro\Http\Controllers\CoworkSidebarController::class, 'data']
        )->name('cowork-sidebar-data');

        // Onda Comments + Audit DB 2026-05-18 — persiste comments do FinCommentsThread
        // em DB + serve audit trail real do Spatie ActivityLog. Tier 0 via session.
        Route::get('/unificado/{tituloId}/comments', [UnificadoController::class, 'comments'])
            ->whereNumber('tituloId')
            ->name('unificado.comments');
        Route::post('/unificado/{tituloId}/comments', [UnificadoController::class, 'addComment'])
            ->whereNumber('tituloId')
            ->name('unificado.comments.add');
        Route::get('/unificado/{tituloId}/audit', [UnificadoController::class, 'auditTrail'])
            ->whereNumber('tituloId')
            ->name('unificado.audit');

        // Fluxo de caixa projetado — Cockpit V2 (US-FIN-014) — protótipo Cowork 2026-05-09
        // Q1-Q4 aprovadas [W] 2026-05-14. Read-only. Ver Index.charter.md + fluxo-visual-comparison.md.
        Route::get('/fluxo', [FluxoController::class, 'index'])->name('fluxo.index');

        // Contas a receber (lista + emitir boleto)
        Route::get('/contas-receber', [ContaReceberController::class, 'index'])->name('contas-receber.index');
        Route::post('/contas-receber/{tituloId}/boleto', [ContaReceberController::class, 'emitirBoleto'])
            ->whereNumber('tituloId')
            ->name('contas-receber.emitir-boleto');

        // Boletos emitidos (lista + cancelar)
        Route::get('/boletos', [BoletoController::class, 'index'])->name('boletos.index');
        Route::post('/boletos/{remessaId}/cancelar', [BoletoController::class, 'cancelar'])
            ->whereNumber('remessaId')
            ->name('boletos.cancelar');

        // Contas a pagar (lista + registrar baixa)
        Route::get('/contas-pagar', [ContaPagarController::class, 'index'])->name('contas-pagar.index');
        Route::post('/contas-pagar/{tituloId}/pagar', [ContaPagarController::class, 'pagar'])
            ->whereNumber('tituloId')
            ->name('contas-pagar.pagar');

        // Contas bancarias — cadastro de complemento de boleto (ADR TECH-0003)
        Route::get('/contas-bancarias', [ContaBancariaController::class, 'index'])->name('contas-bancarias.index');
        Route::post('/contas-bancarias/{accountId}', [ContaBancariaController::class, 'upsert'])
            ->whereNumber('accountId')
            ->name('contas-bancarias.upsert');

        // Extrato bancário — leitura via Banking API (US-RB-046)
        Route::get('/extrato/{contaBancariaId}', [ExtratoController::class, 'index'])
            ->whereNumber('contaBancariaId')
            ->name('extrato.index');

        // Relatórios (DRE / Fluxo / Resumo) — US-FIN-014
        Route::get('/relatorios', [RelatoriosController::class, 'index'])->name('relatorios.index');
        Route::get('/relatorios/export-csv', [RelatoriosController::class, 'exportCsv'])->name('relatorios.export-csv');

        // Alias canonical: /financeiro/dashboard → /financeiro (URL canônica é a raiz)
        Route::redirect('/dashboard', '/financeiro', 301)->name('dashboard.alias');

        // Categorias livres (CRUD complementar ao plano de contas)
        Route::get('/categorias', [CategoriaController::class, 'index'])->name('categorias.index');
        Route::post('/categorias', [CategoriaController::class, 'store'])->name('categorias.store');
        Route::put('/categorias/{id}', [CategoriaController::class, 'update'])
            ->whereNumber('id')
            ->name('categorias.update');
        Route::delete('/categorias/{id}', [CategoriaController::class, 'destroy'])
            ->whereNumber('id')
            ->name('categorias.destroy');
        Route::post('/categorias/{id}/toggle', [CategoriaController::class, 'toggleAtivo'])
            ->whereNumber('id')
            ->name('categorias.toggle');
    });
