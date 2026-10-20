<?php

use Modules\Producao\Http\Controllers\Api\AppApontamentoController;
use Illuminate\Support\Facades\Route;

// Carregado dentro do grupo auth:api + prefixo app + nome app. de routes/api.php.
// Apontamento de produção (P8, tela 02) sobre o ApontamentoOperadorService do Modules/Producao
// (ADR 0431). Contrato: memory/requisitos/AppMobile/api/tela-02-apontamento.md.
// Escrita com Idempotency-Key e throttle 30/min.
Route::prefix('producao')->name('producao.')->group(function () {
    Route::get('/apontamentos/em-andamento', [AppApontamentoController::class, 'emAndamento'])->name('apontamentos.em_andamento');
    // Motivos de pausa ativos (UC-PCV-33); ?posto_id= filtra. O app guarda por posto para pausar offline.
    Route::get('/motivos-pausa', [AppApontamentoController::class, 'motivosPausa'])->name('motivos_pausa');
    Route::post('/apontamentos/iniciar', [AppApontamentoController::class, 'iniciar'])->middleware('throttle:30,1')->name('apontamentos.iniciar');
    Route::post('/apontamentos/{id}/pausar', [AppApontamentoController::class, 'pausar'])
        ->whereNumber('id')->middleware('throttle:30,1')->name('apontamentos.pausar');
    Route::post('/apontamentos/{id}/concluir', [AppApontamentoController::class, 'concluir'])
        ->whereNumber('id')->middleware('throttle:30,1')->name('apontamentos.concluir');
    Route::post('/apontamentos/{id}/corrigir', [AppApontamentoController::class, 'corrigir'])
        ->whereNumber('id')->middleware('throttle:30,1')->name('apontamentos.corrigir');
    // US-PCP-021 · UC-FLX-37 — material consumido na etapa (baixa pelo lote que saiu).
    Route::post('/apontamentos/material', [AppApontamentoController::class, 'material'])->middleware('throttle:30,1')->name('apontamentos.material');
    // QR `oimpresso:p:<business_hash>:<os_id>` ou número digitado; throttle contra varredura de números.
    Route::get('/etiqueta/{codigo}', [AppApontamentoController::class, 'etiqueta'])
        ->where('codigo', '[^/]{1,120}')->middleware('throttle:60,1')->name('etiqueta');
});
