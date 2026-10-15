<?php

use Illuminate\Support\Facades\Route;

// Carregado dentro do grupo auth:api + prefixo app + nome app. de routes/api.php.
// Um arquivo por área do app, para cada PR tocar só o seu (contrato: memory/requisitos/AppMobile/).

// Atendimento WhatsApp (tela 41, P5) por cima da Caixa Unificada da web.
// Contrato: memory/requisitos/AppMobile/api/tela-41-atendimento.md.
Route::get('/atendimento', [\App\Http\Controllers\Api\App\AtendimentoController::class, 'index'])->name('atendimento.index');
Route::get('/atendimento/{id}', [\App\Http\Controllers\Api\App\AtendimentoController::class, 'show'])->whereNumber('id')->name('atendimento.show');
// Envio real ao cliente: Idempotency-Key (app_idempotencia) + throttle de escrita.
Route::post('/atendimento/{id}/mensagens', [\App\Http\Controllers\Api\App\AtendimentoController::class, 'enviar'])
    ->whereNumber('id')->middleware('throttle:20,1')->name('atendimento.mensagens.store');
Route::post('/atendimento/{id}/atalho', [\App\Http\Controllers\Api\App\AtendimentoController::class, 'atalho'])
    ->whereNumber('id')->middleware('throttle:20,1')->name('atendimento.atalho');
