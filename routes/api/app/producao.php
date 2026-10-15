<?php

use App\Http\Controllers\Api\App\ProducaoController;
use Illuminate\Support\Facades\Route;

// Carregado dentro do grupo auth:api + prefixo app + nome app. de routes/api.php.
// Apontamento de produção (P8, tela 02) — contrato: memory/requisitos/AppMobile/api/tela-02-apontamento.md.
// Sem pausa: só iniciar, finalizar e cancelar. Escrita com Idempotency-Key e throttle 30/min.
Route::prefix('producao')->name('producao.')->group(function () {
    Route::get('/apontamentos/em-andamento', [ProducaoController::class, 'emAndamento'])->name('apontamentos.em_andamento');
    Route::post('/apontamentos/iniciar', [ProducaoController::class, 'iniciar'])->middleware('throttle:30,1')->name('apontamentos.iniciar');
    Route::post('/apontamentos/{id}/finalizar', [ProducaoController::class, 'finalizar'])
        ->whereNumber('id')->middleware('throttle:30,1')->name('apontamentos.finalizar');
    Route::post('/apontamentos/{id}/cancelar', [ProducaoController::class, 'cancelar'])
        ->whereNumber('id')->middleware('throttle:30,1')->name('apontamentos.cancelar');
    // QR `oimpresso:p:<business_hash>:<pedido_id>` ou número digitado; throttle contra varredura de números.
    Route::get('/etiqueta/{codigo}', [ProducaoController::class, 'etiqueta'])
        ->where('codigo', '[^/]{1,120}')->middleware('throttle:60,1')->name('etiqueta');
});
