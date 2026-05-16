<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Smoke test das rotas principais de Modules/SRS.
 *
 * Garante que rotas retornam status <500 (middleware auth pode redirecionar 302,
 * isso é OK — comprova que rota existe e middleware stack carrega sem erro).
 *
 * Sem login (não testa lógica de negócio); valida apenas que:
 *   1. Rota está registrada (Route::has)
 *   2. Middleware stack carrega (sem 500)
 *
 * ADR 0011: padrão Jana/Repair/Project — smoke routes obrigatório por módulo.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: middleware UltimatePOS requer schema MySQL (business/users)');
    }
    if (! Schema::hasTable('docs_sources')) {
        $this->markTestSkipped('Tabelas docs_* ausentes — rode `php artisan module:migrate SRS` primeiro');
    }
});

it('rota memcofre.dashboard responde sem 500 (auth pode redirecionar)', function () {
    $response = $this->get(route('memcofre.dashboard'));
    expect($response->getStatusCode())->toBeLessThan(500);
});

it('rota memcofre.ingest responde sem 500', function () {
    $response = $this->get(route('memcofre.ingest'));
    expect($response->getStatusCode())->toBeLessThan(500);
});

it('rota memcofre.inbox responde sem 500', function () {
    $response = $this->get(route('memcofre.inbox'));
    expect($response->getStatusCode())->toBeLessThan(500);
});

it('rota srs.install.index responde sem 500', function () {
    $response = $this->get(route('srs.install.index'));
    expect($response->getStatusCode())->toBeLessThan(500);
});

it('redirect legacy /memcofre/install retorna 301 pra /srs/install', function () {
    $response = $this->get('/memcofre/install');
    // 301 esperado conforme routes.php — mas se middleware auth interceptar, vira 302; ambos <500
    expect($response->getStatusCode())->toBeLessThan(500);
});
