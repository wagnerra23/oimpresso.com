<?php

use Illuminate\Support\Facades\Route;

// Carregado dentro do grupo auth:api + prefixo app + nome app. de routes/api.php.
// Um arquivo por área do app, para cada PR tocar só o seu (contrato: memory/requisitos/AppMobile/).

Route::get('/pedidos', [\App\Http\Controllers\Api\App\PedidosController::class, 'index'])->name('pedidos.index');
Route::get('/pedidos/{id}', [\App\Http\Controllers\Api\App\PedidosController::class, 'show'])->whereNumber('id')->name('pedidos.show');
Route::get('/orcamentos', [\App\Http\Controllers\Api\App\PedidosController::class, 'orcamentos'])->name('orcamentos.index');
Route::get('/producao', [\App\Http\Controllers\Api\App\PedidosController::class, 'producao'])->name('producao.index');
// Avançar etapa do pedido (P1 · tela 22): só a lista segura (AcoesDoPedido), Idempotency-Key e
// etapa_esperada obrigatórias; throttle de escrita. Contrato: api/tela-22-avancar-etapa.md.
Route::post('/pedidos/{id}/acao', [\App\Http\Controllers\Api\App\PedidoAcaoController::class, 'executar'])
    ->whereNumber('id')->middleware('throttle:30,1')->name('pedidos.acao');
