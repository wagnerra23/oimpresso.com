<?php

use Illuminate\Support\Facades\Route;

// Carregado dentro do grupo auth:api + prefixo app + nome app. de routes/api.php.
// Um arquivo por área do app, para cada PR tocar só o seu (contrato: memory/requisitos/AppMobile/).

Route::get('/notificacoes', [\App\Http\Controllers\Api\App\NotificacoesController::class, 'index'])->name('notificacoes.index');
Route::post('/notificacoes/lidas', [\App\Http\Controllers\Api\App\NotificacoesController::class, 'marcarTodasLidas'])->name('notificacoes.lidas');
Route::post('/notificacoes/{id}/lida', [\App\Http\Controllers\Api\App\NotificacoesController::class, 'marcarLida'])->whereUuid('id')->name('notificacoes.lida');
