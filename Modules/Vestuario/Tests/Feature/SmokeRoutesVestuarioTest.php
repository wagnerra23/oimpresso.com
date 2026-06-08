<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Smoke das 3 rotas Install Modules/Vestuario + checagem middleware auth.
 *
 * Valida que:
 *   1. GET /vestuario/install responde <500 (200 ou 302 pra login)
 *   2. GET /vestuario/install/uninstall responde <500
 *   3. GET /vestuario/install/update responde <500
 *   4. Acesso anonimo (sem auth) e redirecionado/blocado pelo middleware
 *      'auth' da stack canonica UltimatePOS — proteção contra exposição
 *      de Install routes
 *
 * Per Routes/web.php: stack ['web','authh','auth','SetSessionData','language',
 * 'timezone','AdminSidebarMenu'].
 *
 * NUNCA usar biz=4 (ROTA LIVRE — Larissa producao) — ADR 0101.
 *
 * @see Modules/Vestuario/Routes/web.php
 * @see Modules/Vestuario/Http/Controllers/InstallController.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: middlewares UltimatePOS (SetSessionData, AdminSidebarMenu) requerem schema MySQL com tables business/users/permissions (ADR 0101)');
    }
    if (! Schema::hasTable('business') || ! Schema::hasTable('users')) {
        $this->markTestSkipped('Tabelas core UltimatePOS (business/users) ausentes — rode migrate primeiro');
    }
});

// ------------------------------------------------------------------
// Smoke routes Install
// ------------------------------------------------------------------

it('GET /vestuario/install responde com status < 500', function () {
    // Sem auth — esperado redirect 302 pra login ou similar < 500.
    // NAO deve crashar com 500 (signal de bug em Controller/middleware).
    $response = $this->get('/vestuario/install');

    expect($response->getStatusCode())->toBeLessThan(500);
});

it('GET /vestuario/install/uninstall responde com status < 500', function () {
    $response = $this->get('/vestuario/install/uninstall');

    expect($response->getStatusCode())->toBeLessThan(500);
});

it('GET /vestuario/install/update responde com status < 500', function () {
    $response = $this->get('/vestuario/install/update');

    expect($response->getStatusCode())->toBeLessThan(500);
});

it('rota /vestuario/install bloqueia acesso anonimo via middleware auth', function () {
    // Sem login — middleware 'auth' da stack canonica UltimatePOS deve
    // interceptar e redirecionar pra /login ou retornar 401/403.
    $response = $this->get('/vestuario/install');

    $statusCode = $response->getStatusCode();
    $isAuthBlocked = in_array($statusCode, [302, 401, 403], true);

    expect($isAuthBlocked)->toBeTrue(
        "Esperado status 302/401/403 (auth middleware) — recebeu {$statusCode}. ".
        'Se 200, middleware auth NAO esta aplicado em /vestuario/install — violacao stack canonica.'
    );
});

it('rota /vestuario/install/uninstall bloqueia acesso anonimo via middleware auth', function () {
    $response = $this->get('/vestuario/install/uninstall');

    $statusCode = $response->getStatusCode();
    $isAuthBlocked = in_array($statusCode, [302, 401, 403], true);

    expect($isAuthBlocked)->toBeTrue(
        "Esperado status 302/401/403 — recebeu {$statusCode}. Uninstall sem auth = grave."
    );
});

it('rota /vestuario/install/update bloqueia acesso anonimo via middleware auth', function () {
    $response = $this->get('/vestuario/install/update');

    $statusCode = $response->getStatusCode();
    $isAuthBlocked = in_array($statusCode, [302, 401, 403], true);

    expect($isAuthBlocked)->toBeTrue(
        "Esperado status 302/401/403 — recebeu {$statusCode}."
    );
});
