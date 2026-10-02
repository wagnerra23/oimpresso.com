<?php

use Illuminate\Support\Facades\Route;

// Carregado dentro do grupo auth:api + prefixo app + nome app. de routes/api.php.
// Um arquivo por área do app, para cada PR tocar só o seu (contrato: memory/requisitos/AppMobile/).

Route::get('/pessoas', [\App\Http\Controllers\Api\App\PessoasController::class, 'index'])->name('pessoas.index');
Route::post('/pessoas', [\App\Http\Controllers\Api\App\PessoasController::class, 'store'])->name('pessoas.store');
Route::get('/pessoas/{id}', [\App\Http\Controllers\Api\App\PessoasController::class, 'show'])->whereNumber('id')->name('pessoas.show');
Route::patch('/pessoas/{id}', [\App\Http\Controllers\Api\App\PessoasController::class, 'update'])->whereNumber('id')->name('pessoas.update');
Route::get('/pessoas/{id}/cadastro', [\App\Http\Controllers\Api\App\PessoasController::class, 'cadastro'])->whereNumber('id')->name('pessoas.cadastro');
// Throttle como o lookup da web (60/min): o ERP fala com o ViaCEP por todos os tenants.
Route::get('/cep/{cep}', [\App\Http\Controllers\Api\App\CepController::class, 'show'])
    ->where('cep', '[0-9-]{1,12}')->middleware('throttle:60,1')->name('cep.show');
