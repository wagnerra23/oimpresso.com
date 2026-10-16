<?php

use Illuminate\Support\Facades\Route;

// Carregado dentro do grupo auth:api + prefixo app + nome app. de routes/api.php.
// Um arquivo por área do app, para cada PR tocar só o seu (contrato: memory/requisitos/AppMobile/).

Route::get('/financeiro', [\App\Http\Controllers\Api\App\FinanceiroController::class, 'index'])->name('financeiro.index');

// Aba Recorrências (chave RECORRENCIAS do app): contratos e contas fixas; interromper para só a geração futura.
Route::get('/financeiro/recorrencias', [\App\Http\Controllers\Api\App\FinanceiroRecorrenciasController::class, 'index'])->name('financeiro.recorrencias');
Route::post('/financeiro/recorrencias/{id}/interromper', [\App\Http\Controllers\Api\App\FinanceiroRecorrenciasController::class, 'interromper'])
    ->whereNumber('id')->name('financeiro.recorrencias.interromper');
