<?php

use Illuminate\Support\Facades\Route;

// Carregado dentro do grupo auth:api + prefixo app + nome app. de routes/api.php.
// Um arquivo por área do app, para cada PR tocar só o seu (contrato: memory/requisitos/AppMobile/).

Route::get('/os', [\App\Http\Controllers\Api\App\OficinaController::class, 'index'])->name('os.index');
Route::get('/os/{id}', [\App\Http\Controllers\Api\App\OficinaController::class, 'show'])->whereNumber('id')->name('os.show');
Route::get('/veiculos', [\App\Http\Controllers\Api\App\OficinaController::class, 'veiculos'])->name('veiculos.index');
