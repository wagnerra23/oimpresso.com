<?php

use Illuminate\Support\Facades\Route;

// Carregado dentro do grupo auth:api + prefixo app + nome app. de routes/api.php.
// Um arquivo por área do app, para cada PR tocar só o seu (contrato: memory/requisitos/AppMobile/).

Route::get('/financeiro', [\App\Http\Controllers\Api\App\FinanceiroController::class, 'index'])->name('financeiro.index');

// + Novo lançamento (chave NOVO_LANCAMENTO do app). Regra mestre: mexe em valor — ver o controller.
Route::post('/financeiro/lancamentos', [\App\Http\Controllers\Api\App\FinanceiroLancamentosController::class, 'store'])->name('financeiro.lancamentos.store');

// Detalhe e ações do lançamento (chave LANCAMENTO_ACOES do app). Regra mestre: baixa e retorno mexem em valor.
Route::controller(\App\Http\Controllers\Api\App\FinanceiroLancamentoAcoesController::class)
    ->prefix('/financeiro/lancamentos/{id}')->whereNumber('id')->name('financeiro.lancamentos.')
    ->group(function () {
        Route::get('/', 'show')->name('show');
        Route::patch('/', 'alterar')->name('alterar');
        Route::post('/baixar', 'baixar')->name('baixar');
        Route::post('/estornar', 'estornar')->name('estornar');
        Route::post('/excluir', 'excluir')->name('excluir');
        Route::post('/reativar', 'reativar')->name('reativar');
        Route::get('/recibo', 'recibo')->name('recibo');
    });
