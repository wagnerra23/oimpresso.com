<?php

use Illuminate\Support\Facades\Route;
use Modules\PaymentGateway\Http\Controllers\InstallController;

/*
|--------------------------------------------------------------------------
| Web Routes — PaymentGateway (Onda 1 esqueleto)
|--------------------------------------------------------------------------
|
| ADR 0170 Onda 1: só Install routes. Ondas 3-4 adicionam webhooks + CRUD
| cobrança + credenciais.
|
*/

Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('paymentgateway')
    ->group(function () {
        Route::get('install', [InstallController::class, 'index']);
        Route::get('install/uninstall', [InstallController::class, 'uninstall']);
        Route::get('install/update', [InstallController::class, 'update']);
    });
