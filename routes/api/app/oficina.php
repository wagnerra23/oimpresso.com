<?php

use Illuminate\Support\Facades\Route;

// Carregado dentro do grupo auth:api + prefixo app + nome app. de routes/api.php.
// Um arquivo por área do app, para cada PR tocar só o seu (contrato: memory/requisitos/AppMobile/).

Route::get('/os', [\App\Http\Controllers\Api\App\OficinaController::class, 'index'])->name('os.index');
// Nova OS de mecânica (tela 07): throttle de escrita.
Route::post('/os', [\App\Http\Controllers\Api\App\OficinaController::class, 'store'])->middleware('throttle:30,1')->name('os.store');
Route::get('/os/{id}', [\App\Http\Controllers\Api\App\OficinaController::class, 'show'])->whereNumber('id')->name('os.show');
// Avançar etapa (tela 03): só as ações da linha principal; throttle de escrita.
Route::post('/os/{id}/acoes/{chave}', [\App\Http\Controllers\Api\App\OficinaController::class, 'executarAcao'])
    ->whereNumber('id')->where('chave', '[a-z_]{1,80}')->middleware('throttle:30,1')->name('os.acoes.executar');
Route::get('/veiculos', [\App\Http\Controllers\Api\App\OficinaController::class, 'veiculos'])->name('veiculos.index');
Route::get('/veiculos/opcoes', [\App\Http\Controllers\Api\App\OficinaController::class, 'opcoesVeiculo'])->name('veiculos.opcoes');
// Consulta de placa (pedido [W] 2026-10-05): pode ser paga por consulta → throttle baixo.
Route::get('/veiculos/consulta-placa/{placa}', [\App\Http\Controllers\Api\App\OficinaController::class, 'consultaPlaca'])
    ->where('placa', '[A-Za-z0-9 .-]{1,12}')->middleware('throttle:10,1')->name('veiculos.consulta_placa');
// Novo veículo (pedido [W] 2026-10-05): throttle de escrita, como a nova OS.
Route::post('/veiculos', [\App\Http\Controllers\Api\App\OficinaController::class, 'storeVeiculo'])->middleware('throttle:30,1')->name('veiculos.store');
Route::get('/veiculos/{id}/os', [\App\Http\Controllers\Api\App\OficinaController::class, 'veiculoOs'])->whereNumber('id')->name('veiculos.os');
