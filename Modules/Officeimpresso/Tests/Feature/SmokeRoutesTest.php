<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Smoke test das rotas principais do modulo Officeimpresso.
 *
 * Validacao MINIMA — apenas:
 *   - middleware auth bloqueia request anonimo (302 redirect pra login)
 *   - rota nao explode com 500
 *
 * NAO testa autorizacao fina nem dados — soh saude do roteamento.
 *
 * @see Modules/Officeimpresso/Routes/web.php
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: stack UltimatePOS requer schema MySQL (ADR 0101)');
    }
    if (! Schema::hasTable('users')) {
        $this->markTestSkipped('Tabela users ausente — schema UltimatePOS necessario');
    }
});

it('rota licenca_computador.index redireciona anonimo pra login (status <500)', function () {
    // Sem autenticacao, middleware auth redirect — esperamos 302 ou 401, NUNCA 500
    $response = $this->get(route('licenca_computador.index'));

    expect($response->status())->toBeLessThan(500);
    expect($response->status())->toBeIn([302, 401, 403]);
});

it('rota computadores redireciona anonimo pra login (status <500)', function () {
    $response = $this->get(route('computadores'));

    expect($response->status())->toBeLessThan(500);
    expect($response->status())->toBeIn([302, 401, 403]);
});

it('rota licenca_log.index redireciona anonimo pra login (status <500)', function () {
    $response = $this->get(route('licenca_log.index'));

    expect($response->status())->toBeLessThan(500);
    expect($response->status())->toBeIn([302, 401, 403]);
});

it('rota officeimpresso.catalogue-qr redireciona anonimo pra login (status <500)', function () {
    $response = $this->get(route('officeimpresso.catalogue-qr'));

    expect($response->status())->toBeLessThan(500);
    expect($response->status())->toBeIn([302, 401, 403]);
});

it('rota officeimpresso.install redireciona anonimo pra login (status <500)', function () {
    $response = $this->get(route('officeimpresso.install'));

    expect($response->status())->toBeLessThan(500);
    expect($response->status())->toBeIn([302, 401, 403]);
});
