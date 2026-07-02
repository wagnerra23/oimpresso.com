<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/**
 * Smoke tests de rotas publicas Modules/ConsultaOs.
 *
 * Portal publico (sem auth) — espelha Modules/Repair/Routes/web.php (/repair-status).
 * Implementacao atual usa mockData() in-memory no Controller — sem dependencia DB
 * pra rotas publicas, mas pode ser que TestCase boot precise de schema.
 *
 * Refs: ADR 0101 (tests com biz=1 quando aplicavel — nao se aplica aqui pois rotas
 * publicas nao tem sessao de business).
 */

// Guard SQLite: se boot do TestCase falhar em SQLite (config UltimatePOS), skip.
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: TestCase UltimatePOS requer schema MySQL — Wagner Pest local segue mandatory (ADR 0101).');
    }
});

it('rota publica GET /consulta-os retorna 200 (Inertia Index)', function () {
    $response = $this->get('/consulta-os');

    // Pagina Inertia renderiza com 200 OK. Em testes Inertia retorna JSON
    // ou HTML dependendo do header — qualquer 200 satisfaz smoke.
    expect($response->getStatusCode())->toBe(200);
});

it('rota publica GET /consulta-os/buscar com numero conhecido retorna found=true', function () {
    // mockData() do Controller tem chave '4821' (Acme Comercio).
    $response = $this->getJson('/consulta-os/buscar?numero=4821');

    $response->assertStatus(200);
    $response->assertJson(['found' => true]);
    // Grafia canônica do mock SEM acento (MockConsultaOsRepository::class '4821').
    expect($response->json('os.client'))->toBe('Acme Comercio Ltda');
});

it('rota publica GET /consulta-os/buscar com numero inexistente retorna 404', function () {
    $response = $this->getJson('/consulta-os/buscar?numero=99999');

    $response->assertStatus(404);
    $response->assertJson(['found' => false]);
});

it('rota publica GET /consulta-os/buscar sem numero retorna 422 (validation)', function () {
    $response = $this->getJson('/consulta-os/buscar');

    // Laravel valida 'required' no input — retorna 422 em JSON.
    $response->assertStatus(422);
});
