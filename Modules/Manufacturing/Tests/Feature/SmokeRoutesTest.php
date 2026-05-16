<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Smoke tests das rotas web Manufacturing.
 *
 * Objetivo: garantir que nenhuma rota do módulo retorna 5xx (erro de servidor)
 * mesmo sem usuário autenticado. Sem auth o middleware `auth` redireciona
 * pra /login (302) — comportamento esperado. O que NÃO pode acontecer é:
 *
 *   - 500 (Internal Server Error — bug)
 *   - 503 (Service Unavailable — daemon morto)
 *   - Exception não-tratada
 *
 * Não tentamos validar payload/conteúdo aqui — isso é responsabilidade de
 * tests de integração com auth real (out of scope desta smoke layer).
 *
 * @see Modules/Manufacturing/Routes/web.php
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: middlewares UltimatePOS dependem schema MySQL. ADR 0101.');
    }
    if (! Schema::hasTable('mfg_recipes')) {
        $this->markTestSkipped('Manufacturing não instalado nesta base.');
    }
});

it('GET /manufacturing/recipe (recipe.index) não retorna 5xx', function () {
    $response = $this->get('/manufacturing/recipe');

    // Aceito: 200 (autenticado), 302 (redirect pra /login), 401/403 (sem permissão).
    // Inaceitável: 5xx.
    expect($response->getStatusCode())->toBeLessThan(500);
});

it('GET /manufacturing/production (production.index) não retorna 5xx', function () {
    $response = $this->get('/manufacturing/production');

    expect($response->getStatusCode())->toBeLessThan(500);
});

it('GET /manufacturing/settings (settings.index) não retorna 5xx', function () {
    $response = $this->get('/manufacturing/settings');

    expect($response->getStatusCode())->toBeLessThan(500);
});

it('GET /manufacturing/report não retorna 5xx', function () {
    $response = $this->get('/manufacturing/report');

    expect($response->getStatusCode())->toBeLessThan(500);
});

it('rotas Manufacturing sem auth redirecionam pra /login (middleware auth ativo)', function () {
    // Pelo menos uma das rotas deve disparar fluxo de auth quando convidado.
    // Se NENHUMA redireciona, o stack de middlewares ['web', 'auth', ...] está quebrado.
    $rotas = [
        '/manufacturing/recipe',
        '/manufacturing/production',
        '/manufacturing/settings',
    ];

    $algumaRedireciona = false;
    foreach ($rotas as $rota) {
        $r = $this->get($rota);
        if ($r->getStatusCode() === 302) {
            $algumaRedireciona = true;
            break;
        }
    }

    expect($algumaRedireciona)->toBeTrue();
});
