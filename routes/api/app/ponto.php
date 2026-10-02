<?php

use Illuminate\Support\Facades\Route;

// Carregado dentro do grupo auth:api + prefixo app + nome app. de routes/api.php.
// Um arquivo por área do app, para cada PR tocar só o seu (contrato: memory/requisitos/AppMobile/).

// Marcações a validar (tela 39): fila do gestor do REP-P. Id é UUID (ponto_marcacoes).
Route::get('/ponto/aprovacoes', [\App\Http\Controllers\Api\App\PontoAprovacoesController::class, 'index'])->name('ponto.aprovacoes.index');
Route::post('/ponto/aprovacoes/{id}/validar', [\App\Http\Controllers\Api\App\PontoAprovacoesController::class, 'validar'])->whereUuid('id')->name('ponto.aprovacoes.validar');
Route::post('/ponto/aprovacoes/{id}/recusar', [\App\Http\Controllers\Api\App\PontoAprovacoesController::class, 'recusar'])->whereUuid('id')->name('ponto.aprovacoes.recusar');
