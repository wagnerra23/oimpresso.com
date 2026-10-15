<?php

use App\Http\Controllers\Api\App\OsArquivosController;
use Illuminate\Support\Facades\Route;

// Carregado dentro do grupo auth:api + prefixo app + nome app. de routes/api.php.
// Anexos da OS (P2, telas 03 e 22). Contrato: memory/requisitos/AppMobile/api/tela-03-anexos.md.

Route::get('/os/{id}/arquivos', [OsArquivosController::class, 'index'])->whereNumber('id')->name('os.arquivos.index');
// Envio: throttle de escrita, Idempotency-Key obrigatória.
Route::post('/os/{id}/arquivos', [OsArquivosController::class, 'store'])
    ->whereNumber('id')->middleware('throttle:30,1')->name('os.arquivos.store');
// Conteúdo pela URL assinada (10 min): sem token, porque um <img> não manda o token. A
// assinatura cobre o business do anexo; adulterar qualquer parâmetro dá 403.
Route::get('/arquivos/{arquivo}/conteudo', [OsArquivosController::class, 'conteudo'])
    ->whereNumber('arquivo')->withoutMiddleware('auth:api')->middleware(['signed', 'throttle:60,1'])
    ->name('arquivos.conteudo');
