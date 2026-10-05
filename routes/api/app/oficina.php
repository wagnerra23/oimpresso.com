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
Route::get('/veiculos/{id}/os', [\App\Http\Controllers\Api\App\OficinaController::class, 'veiculoOs'])->whereNumber('id')->name('veiculos.os');
