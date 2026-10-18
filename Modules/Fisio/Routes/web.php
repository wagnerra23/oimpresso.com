<?php

use Modules\Fisio\Http\Controllers\InstallController;
use Modules\Fisio\Http\Middleware\FisioLiberado;

/*
 * Esqueleto (ADR 0437, US-FISIO-002).
 *
 * 1) Rotas Install 1-clique (ADR 0024 / BaseModuleInstallController). Ficam FORA do
 *    gate do pacote: instalar acontece antes de qualquer empresa ter o pacote. Sem
 *    elas o helper action() em Install/ModulesController vira '#' e o botão "Install"
 *    de /manage-modules não faz nada.
 */
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('fisio')
    ->group(function () {
        Route::post('install',           [InstallController::class, 'install']);
        Route::get('install',           [InstallController::class, 'index']);
        Route::match(['get', 'post'], 'install/uninstall', [InstallController::class, 'uninstall']);
        Route::match(['get', 'post'], 'install/update',    [InstallController::class, 'update']);
    });

/*
 * 2) Área da clínica: TODA rota autenticada do Fisio entra neste grupo. FisioLiberado
 *    devolve 403 para empresa sem o pacote `fisio_module` ou usuário sem `fisio.access`.
 *    As rotas de negócio (Painel, Pacientes, prontuário, programas, biblioteca) chegam
 *    com as US seguintes (US-FISIO-003 em diante), sempre dentro deste grupo.
 *
 *    O portal público do paciente (/fisio/p/{token}) NÃO entra aqui: tem grupo próprio,
 *    sem auth, na US do portal (US-FISIO-007).
 */
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin', FisioLiberado::class])
    ->prefix('fisio')
    ->name('fisio.')
    ->group(function () {
        //
    });