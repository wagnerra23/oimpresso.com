<?php

use Illuminate\Support\Facades\Route;

// Carregado dentro do grupo auth:api + prefixo app + nome app. de routes/api.php.
// Um arquivo por área do app, para cada PR tocar só o seu (contrato: memory/requisitos/AppMobile/).

// Chat com a Jana (tela 25). Mesmo teto do chat web (60/min): cada mensagem chama o LLM.
Route::post('/chat', [\App\Http\Controllers\Api\App\ChatController::class, 'enviar'])->middleware('throttle:60,1')->name('chat.enviar');
Route::get('/chat/{conversa_id}', [\App\Http\Controllers\Api\App\ChatController::class, 'mostrar'])->whereNumber('conversa_id')->name('chat.mostrar');
