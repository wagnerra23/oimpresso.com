<?php

use Illuminate\Support\Facades\Route;

// Carregado dentro do grupo auth:api + prefixo app + nome app. de routes/api.php.
// Um arquivo por área do app, para cada PR tocar só o seu (contrato: memory/requisitos/AppMobile/).

Route::get('/venda/produtos', [\App\Http\Controllers\Api\App\VendaRapidaController::class, 'produtos'])->name('venda.produtos');
