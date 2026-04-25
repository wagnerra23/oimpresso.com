<?php

use Illuminate\Support\Facades\Route;
use Modules\Financeiro\Http\Controllers\BoletoController;
use Modules\Financeiro\Http\Controllers\CategoriaController;
use Modules\Financeiro\Http\Controllers\ContaBancariaController;
use Modules\Financeiro\Http\Controllers\ContaPagarController;
use Modules\Financeiro\Http\Controllers\ContaReceberController;
use Modules\Financeiro\Http\Controllers\DashboardController;
use Modules\Financeiro\Http\Controllers\InstallController;

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

// Rotas operacionais do módulo
Route::middleware(['web', 'auth', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('financeiro')
    ->name('financeiro.')
    ->group(function () {
        // Dashboard unificado (US-FIN-013)
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

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
