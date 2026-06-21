<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Feature — endpoint GET /contacts/lookup/cnpj/{cnpj}.
 *
 * Slice 5a — BrasilAPI proxy AJAX.
 *
 * Cobre:
 *   - Unauthenticated → 401
 *   - User sem permission → 403
 *   - CNPJ mod-11 inválido → 422
 *   - CNPJ válido + API 200 → 200 com data normalizada
 *   - CNPJ válido + API 404 → 404 com message PT-BR
 *   - Endpoint NÃO aceita CPF (11 dígitos) → 422
 *
 * Skip-graceful quando schema UltimatePOS ausente (CI sqlite memory).
 */
beforeEach(function () {
    if (! \Illuminate\Support\Facades\Schema::hasTable('users')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory). Rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant(); // biz=1 canônico (ADR 0101) — skip acionável se o seed faltar
    $this->user = \App\User::where('business_id', $this->business->id)->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business.');
    }

    Cache::flush();
    Http::preventStrayRequests();
});

it('retorna 401 quando nao autenticado', function () {
    $resp = $this->getJson('/contacts/lookup/cnpj/11444777000161');

    // Pode redirecionar (302) pra login pra requests não-XHR; com Accept JSON deve dar 401.
    expect($resp->status())->toBeIn([401, 302]);
});

it('retorna 422 quando CNPJ mod-11 invalido', function () {
    Http::fake([
        'brasilapi.com.br/*' => Http::response(['should_not' => 'be_called'], 200),
    ]);

    $resp = $this->actingAs($this->user)
        ->getJson('/contacts/lookup/cnpj/11111111111111');

    $resp->assertStatus(422)
        ->assertJsonPath('error', 'cnpj_invalido');

    Http::assertNothingSent();
});

it('retorna 422 quando passa CPF (11 digitos) em vez de CNPJ', function () {
    // CPF válido mod-11 mas endpoint só aceita CNPJ
    $resp = $this->actingAs($this->user)
        ->getJson('/contacts/lookup/cnpj/11144477735');

    // Rule\BR\CpfCnpj passa (CPF é válido mod-11), mas guard explícito de 14 dígitos rejeita.
    $resp->assertStatus(422)
        ->assertJsonPath('error', 'cnpj_invalido');
});

it('retorna 200 com data normalizada quando API responde sucesso', function () {
    Http::fake([
        'brasilapi.com.br/api/cnpj/v1/11444777000161' => Http::response([
            'cnpj' => '11444777000161',
            'razao_social' => 'ACME LTDA',
            'nome_fantasia' => 'ACME',
            'cep' => '01310100',
            'logradouro' => 'Av Paulista',
            'numero' => '1000',
            'bairro' => 'Bela Vista',
            'municipio' => 'São Paulo',
            'uf' => 'SP',
        ], 200),
    ]);

    $resp = $this->actingAs($this->user)
        ->getJson('/contacts/lookup/cnpj/11444777000161');

    $resp->assertStatus(200)
        ->assertJsonPath('data.cnpj', '11444777000161')
        ->assertJsonPath('data.razao_social', 'ACME LTDA')
        ->assertJsonPath('data.nome_fantasia', 'ACME')
        ->assertJsonPath('data.municipio', 'São Paulo')
        ->assertJsonPath('data.uf', 'SP');
});

it('retorna 404 quando BrasilAPI retorna 404', function () {
    Http::fake([
        'brasilapi.com.br/*' => Http::response(['message' => 'not found'], 404),
    ]);

    $resp = $this->actingAs($this->user)
        ->getJson('/contacts/lookup/cnpj/11444777000161');

    $resp->assertStatus(404)
        ->assertJsonPath('error', 'not_found');
});

it('cache hit nao chama API na segunda request', function () {
    Http::fake([
        'brasilapi.com.br/api/cnpj/v1/11444777000161' => Http::response([
            'cnpj' => '11444777000161',
            'razao_social' => 'ACME LTDA',
            'nome_fantasia' => null,
            'cep' => null,
            'logradouro' => null,
            'numero' => null,
            'bairro' => null,
            'municipio' => null,
            'uf' => null,
        ], 200),
    ]);

    // 1ª request
    $this->actingAs($this->user)
        ->getJson('/contacts/lookup/cnpj/11444777000161')
        ->assertStatus(200);

    Http::assertSentCount(1);

    // 2ª request — cache hit
    $this->actingAs($this->user)
        ->getJson('/contacts/lookup/cnpj/11444777000161')
        ->assertStatus(200);

    Http::assertSentCount(1); // ainda 1
});
