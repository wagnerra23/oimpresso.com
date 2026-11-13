<?php

use Illuminate\Support\Facades\Route;
use Modules\Associacoes\Http\Controllers\InstallController;
use Modules\Associacoes\Http\Controllers\PortalAssociadaController;
use Modules\Associacoes\Http\Controllers\PortalLinkController;
use Modules\Associacoes\Http\Middleware\AssociacoesLiberado;

/*
 * Rotas Install 1-clique (ADR 0024). Ficam fora do gate do pacote: instalar acontece antes
 * de qualquer empresa ter o pacote. As telas entram nas ondas 3 a 5 da ADR 0451.
 */
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('associacoes')
    ->group(function () {
        Route::post('install', [InstallController::class, 'install']);
        Route::get('install', [InstallController::class, 'index']);
        Route::match(['get', 'post'], 'install/uninstall', [InstallController::class, 'uninstall']);
        Route::match(['get', 'post'], 'install/update', [InstallController::class, 'update']);
    });

/*
 * Telas do módulo (ADR 0451). Todas atrás do pacote + `associacoes.access` (AssociacoesLiberado);
 * o alcance por associação (D4) é aplicado em cada consulta por AssociacoesAcesso::restringir().
 * Cada onda escreve só no seu bloco, para sessões paralelas não colidirem.
 */
Route::middleware(['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin', AssociacoesLiberado::class])
    ->prefix('associacoes')
    ->name('associacoes.')
    ->group(function () {
        // ── onda 3 · associações e documentos (US-ASSOC-002) ──

        // ── onda 4 · associadas e consentimentos (US-ASSOC-003) ──

        // ── onda 5 · painel da rede (US-ASSOC-004) ──
    });

/*
 * ── onda 6 · área da associada no app (US-ASSOC-005 · ADR 0451 D6) ──
 *
 * 6a) Gerar e revogar o link + PIN. Mesmo portão das telas (pacote + `associacoes.access`); o
 *     controller exige `associacoes.editar` e o alcance D4 sobre a associação da associada.
 *     O botão entra na tela de associadas da onda 4 depois do merge.
 */
Route::middleware(['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin', AssociacoesLiberado::class])
    ->prefix('associacoes')
    ->name('associacoes.')
    ->group(function () {
        Route::post('associadas/{id}/portal', [PortalLinkController::class, 'store'])->whereNumber('id')->name('associadas.portal.store');
        Route::delete('associadas/{id}/portal', [PortalLinkController::class, 'destroy'])->whereNumber('id')->name('associadas.portal.destroy');
    });

/*
 * 6b) Área PÚBLICA da associada: sem auth, sem SetSessionData, sem o middleware do pacote — a
 *     associada não é usuária do sistema. O business viaja ASSINADO no token e cada leitura
 *     filtra por ele (PortalAssociadaService, que também confere o pacote). Throttle por IP contra
 *     força bruta, além da trava de 5 PINs errados por 30 min que mora na linha do token.
 */
Route::middleware(['web', 'throttle:60,1'])
    ->prefix('associacoes/m/{token}')
    ->name('associacoes.membro.')
    ->where(['token' => '[A-Za-z0-9_\-\.]{1,512}'])
    ->group(function () {
        Route::get('/', [PortalAssociadaController::class, 'show'])->name('show');
        Route::post('pin', [PortalAssociadaController::class, 'pin'])->name('pin');
        Route::delete('consentimentos/{finalidade}', [PortalAssociadaController::class, 'revogarConsentimento'])
            ->where('finalidade', '[a-z_]{1,40}')->name('consentimentos.destroy');
    });
