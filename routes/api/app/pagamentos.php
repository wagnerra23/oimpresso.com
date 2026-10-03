<?php

use Illuminate\Support\Facades\Route;

// Carregado dentro do grupo auth:api + prefixo app + nome app. de routes/api.php.
// Um arquivo por área do app, para cada PR tocar só o seu (contrato: memory/requisitos/AppMobile/).

// Escrita da tela 15 (mexe em valor — regra mestre; valor = saldo em aberto, decisão [W] 2026-10-02).
Route::get('/pagamentos/referencias', [\App\Http\Controllers\Api\App\PagamentosEscritaController::class, 'referencias'])->name('pagamentos.referencias');
Route::post('/pagamentos', [\App\Http\Controllers\Api\App\PagamentosEscritaController::class, 'store'])->middleware('throttle:20,1')->name('pagamentos.store');
Route::post('/pagamentos/{id}/consultar', [\App\Http\Controllers\Api\App\PagamentosEscritaController::class, 'consultar'])->whereNumber('id')->middleware('throttle:30,1')->name('pagamentos.consultar');
Route::post('/pagamentos/{id}/cancelar', [\App\Http\Controllers\Api\App\PagamentosEscritaController::class, 'cancelar'])->whereNumber('id')->middleware('throttle:20,1')->name('pagamentos.cancelar');
Route::get('/pagamentos', [\App\Http\Controllers\Api\App\PagamentosController::class, 'index'])->name('pagamentos.index');
