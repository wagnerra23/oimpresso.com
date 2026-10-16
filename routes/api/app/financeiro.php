<?php

use Illuminate\Support\Facades\Route;

// Carregado dentro do grupo auth:api + prefixo app + nome app. de routes/api.php.
// Um arquivo por área do app, para cada PR tocar só o seu (contrato: memory/requisitos/AppMobile/).

Route::get('/financeiro', [\App\Http\Controllers\Api\App\FinanceiroController::class, 'index'])->name('financeiro.index');

// + Novo lançamento (chave NOVO_LANCAMENTO do app). Regra mestre: mexe em valor — ver o controller.
Route::post('/financeiro/lancamentos', [\App\Http\Controllers\Api\App\FinanceiroLancamentosController::class, 'store'])->name('financeiro.lancamentos.store');
