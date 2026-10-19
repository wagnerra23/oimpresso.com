<?php

use Illuminate\Support\Facades\Route;

// Carregado dentro do grupo auth:api + prefixo app + nome app. de routes/api.php.
// Um arquivo por área do app, para cada PR tocar só o seu (contrato: memory/requisitos/AppMobile/).

Route::get('/financeiro', [\App\Http\Controllers\Api\App\FinanceiroController::class, 'index'])->name('financeiro.index');

// Aba Recorrências (chave RECORRENCIAS do app): contratos e contas fixas; interromper para só a geração futura.
Route::get('/financeiro/recorrencias', [\App\Http\Controllers\Api\App\FinanceiroRecorrenciasController::class, 'index'])->name('financeiro.recorrencias');
Route::post('/financeiro/recorrencias/{id}/interromper', [\App\Http\Controllers\Api\App\FinanceiroRecorrenciasController::class, 'interromper'])
    ->whereNumber('id')->name('financeiro.recorrencias.interromper');

// + Novo lançamento (chave NOVO_LANCAMENTO do app). Regra mestre: mexe em valor — ver o controller.
Route::post('/financeiro/lancamentos', [\App\Http\Controllers\Api\App\FinanceiroLancamentosController::class, 'store'])->name('financeiro.lancamentos.store');

// Detalhe e ações do lançamento (chave LANCAMENTO_ACOES do app). Regra mestre: baixa e retorno mexem em valor.
Route::controller(\Modules\Financeiro\Http\Controllers\Api\FinanceiroLancamentoAcoesController::class)
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
