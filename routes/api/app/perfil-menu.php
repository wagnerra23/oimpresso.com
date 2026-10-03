<?php

use Illuminate\Support\Facades\Route;

// Carregado dentro do grupo auth:api + prefixo app + nome app. de routes/api.php.
// Um arquivo por área do app, para cada PR tocar só o seu (contrato: memory/requisitos/AppMobile/).

Route::put('/perfil-menu', [\App\Http\Controllers\Api\App\PerfilMenuController::class, 'update'])->name('perfil-menu.update');
