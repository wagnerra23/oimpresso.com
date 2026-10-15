<?php

use Illuminate\Support\Facades\Route;

// Carregado dentro do grupo auth:api + prefixo app + nome app. de routes/api.php.
// Orçamento rápido (tela 04 · P4). REGRA MESTRE (valor): o total é sempre do ERP, com dupla prova.
// Contrato: memory/requisitos/AppMobile/api/tela-04-orcamento-rapido.md.

Route::get('/orcamentos/materiais', [\App\Http\Controllers\Api\App\OrcamentosController::class, 'materiais'])->name('orcamentos.materiais');
// O app recalcula a cada digitação (debounce 500 ms): throttle maior que o de escrita.
Route::post('/orcamentos/calcular', [\App\Http\Controllers\Api\App\OrcamentosController::class, 'calcular'])->middleware('throttle:60,1')->name('orcamentos.calcular');
Route::post('/orcamentos', [\App\Http\Controllers\Api\App\OrcamentosController::class, 'store'])->middleware('throttle:20,1')->name('orcamentos.store');
