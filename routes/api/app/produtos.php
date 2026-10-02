<?php

use Illuminate\Support\Facades\Route;

// Carregado dentro do grupo auth:api + prefixo app + nome app. de routes/api.php.
// Um arquivo por área do app, para cada PR tocar só o seu (contrato: memory/requisitos/AppMobile/).

Route::get('/produtos', [\App\Http\Controllers\Api\App\ProdutosController::class, 'produtos'])->name('produtos.index');
Route::get('/estoque', [\App\Http\Controllers\Api\App\ProdutosController::class, 'estoque'])->name('estoque.index');
Route::get('/estoque/{id}', [\App\Http\Controllers\Api\App\ProdutosController::class, 'estoqueItem'])->whereNumber('id')->name('estoque.show');
