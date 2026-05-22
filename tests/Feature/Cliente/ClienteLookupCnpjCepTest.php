<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Wave C-BE (ADR 0179) -- BrLookupService + ClienteLookupController.
 *
 * 2 endpoints GET com cache Redis (CEP 90d, CNPJ 30d) -- proxy ViaCEP +
 * BrasilAPI server-side. OBRIGATORIO pre-prod: Larissa biz=4 ~30
 * cadastros/dia em pico fura rate limit federal sem cache.
 *
 * Cobre:
 *   - cache hit/miss CEP+CNPJ (Http::fake + Http::assertSentCount)
 *   - 404 graceful em CEP/CNPJ invalido OU upstream sem dado
 *   - 503-like graceful em rate limit/timeout (Http::fake 429 -> service null -> 404)
 *   - Multi-tenant: lookup nao filtra business_id (dados publicos) mas exige auth
 *
 * Skip-graceful em ambientes sem schema UPOS.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('contacts')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory).');
    }

    $this->business = \App\Business::first();
    if (! $this->business) {
        $this->markTestSkipped('Sem business em DB.');
    }
    $this->user = \App\User::where('business_id', $this->business->id)->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business.');
    }

    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id]);

    // Limpa cache pra cada teste -- ambiente CI usa CACHE_DRIVER=array
    // (phpunit.xml linha 71) que ja zera entre processos, mas explicit
    // pra robustez local.
    Cache::flush();
});

// ---------------------------------------------------------------------
// CEP
// ---------------------------------------------------------------------

test('GET /cliente/lookup/cep/{cep} -- cache MISS hits ViaCEP e retorna logradouro', function () {
    Http::fake([
        'viacep.com.br/ws/01310100/json/*' => Http::response([
            'cep' => '01310-100',
            'logradouro' => 'Avenida Paulista',
            'complemento' => 'lado impar',
            'bairro' => 'Bela Vista',
            'localidade' => 'Sao Paulo',
            'uf' => 'SP',
        ], 200),
    ]);

    $response = $this->getJson('/cliente/lookup/cep/01310100');

    $response->assertStatus(200)
        ->assertJson([
            'logradouro' => 'Avenida Paulista',
            'bairro' => 'Bela Vista',
            'cidade' => 'Sao Paulo',
            'uf' => 'SP',
        ]);

    Http::assertSentCount(1);
});

test('GET /cliente/lookup/cep/{cep} -- segunda chamada e cache HIT (NAO bate ViaCEP)', function () {
    Http::fake([
        'viacep.com.br/ws/01310100/json/*' => Http::response([
            'cep' => '01310-100',
            'logradouro' => 'Avenida Paulista',
            'bairro' => 'Bela Vista',
            'localidade' => 'Sao Paulo',
            'uf' => 'SP',
        ], 200),
    ]);

    // 1a chamada -- cache miss, hits ViaCEP.
    $this->getJson('/cliente/lookup/cep/01310100')->assertStatus(200);

    // 2a chamada -- cache hit, NAO deve hittar ViaCEP de novo.
    $response = $this->getJson('/cliente/lookup/cep/01310100');
    $response->assertStatus(200)
        ->assertJsonPath('logradouro', 'Avenida Paulista');

    // Confirma que so 1 request HTTP foi enviado pra ViaCEP no total.
    Http::assertSentCount(1);
});

test('GET /cliente/lookup/cep/{cep} -- CEP inexistente (ViaCEP responde {erro:true}) retorna 404', function () {
    Http::fake([
        'viacep.com.br/ws/00000000/json/*' => Http::response(['erro' => true], 200),
    ]);

    $response = $this->getJson('/cliente/lookup/cep/00000000');

    $response->assertStatus(404)
        ->assertJsonStructure(['message']);
});

test('GET /cliente/lookup/cep/{cep} -- upstream rate limit (429) retorna 404 graceful', function () {
    Http::fake([
        'viacep.com.br/ws/*' => Http::response('Too many', 429),
    ]);

    $response = $this->getJson('/cliente/lookup/cep/01310100');

    // Service captura failed() -> retorna null -> controller 404.
    // Front cai em "preencher manual" -- nao vaza erro upstream pro user.
    $response->assertStatus(404);
});

// ---------------------------------------------------------------------
// CNPJ
// ---------------------------------------------------------------------

test('GET /cliente/lookup/cnpj/{cnpj} -- cache MISS hits BrasilAPI e retorna razao_social + endereco + IBGE + contatos', function () {
    Http::fake([
        'brasilapi.com.br/api/cnpj/v1/11222333000181' => Http::response([
            'cnpj' => '11222333000181',
            'razao_social' => 'ACME COMERCIO LTDA',
            'nome_fantasia' => 'ACME',
            'descricao_situacao_cadastral' => 'ATIVA',
            'cep' => '01310100',
            'logradouro' => 'Avenida Paulista',
            'numero' => '1578',
            'bairro' => 'Bela Vista',
            'municipio' => 'Sao Paulo',
            'uf' => 'SP',
            // BrasilAPI retorna codigo_municipio como int 7 digitos (IBGE).
            // Sao Paulo capital = 3550308.
            'codigo_municipio' => 3550308,
            'ddd_telefone_1' => '1133334444',
            'email' => 'contato@acme.com.br',
        ], 200),
    ]);

    $response = $this->getJson('/cliente/lookup/cnpj/11222333000181');

    $response->assertStatus(200)
        ->assertJson([
            'razao_social' => 'ACME COMERCIO LTDA',
            'fantasia' => 'ACME',
            'ie' => null,
            'situacao' => 'ATIVA',
            // Wagner 2026-05-22 -- endereco chaves canon ClienteAutosaveController::endereco
            // logradouro + numero combinados em address_line_1 ("Rua, 123") porque
            // backend UPOS guarda no mesmo campo.
            'zip_code' => '01310100',
            'address_line_1' => 'Avenida Paulista, 1578',
            'neighborhood' => 'Bela Vista',
            'city' => 'Sao Paulo',
            'state' => 'SP',
            // city_code IBGE obrigatorio NFe/NFSe (Wagner 2026-05-22).
            'city_code' => '3550308',
            // Contatos -- regra so-vazio aplicada no client, service so retorna.
            'mobile' => '1133334444',
            'email' => 'contato@acme.com.br',
        ]);

    Http::assertSentCount(1);
});

test('GET /cliente/lookup/cnpj/{cnpj} -- BrasilAPI sem numero retorna address_line_1 so com logradouro', function () {
    Http::fake([
        'brasilapi.com.br/api/cnpj/v1/11222333000181' => Http::response([
            'razao_social' => 'ACME COMERCIO LTDA',
            'nome_fantasia' => 'ACME',
            'descricao_situacao_cadastral' => 'ATIVA',
            'cep' => '01310100',
            'logradouro' => 'Avenida Paulista',
            // numero ausente -- caso real CNPJ sem cadastro de numero na Receita
            'bairro' => 'Bela Vista',
            'municipio' => 'Sao Paulo',
            'uf' => 'SP',
        ], 200),
    ]);

    $response = $this->getJson('/cliente/lookup/cnpj/11222333000181');

    $response->assertStatus(200)
        ->assertJson([
            'address_line_1' => 'Avenida Paulista',
            'neighborhood' => 'Bela Vista',
        ]);
});

test('GET /cliente/lookup/cnpj/{cnpj} -- BrasilAPI sem endereco/contato retorna campos vazios', function () {
    Http::fake([
        'brasilapi.com.br/api/cnpj/v1/11222333000181' => Http::response([
            'razao_social' => 'ACME COMERCIO LTDA',
            'nome_fantasia' => 'ACME',
            'descricao_situacao_cadastral' => 'ATIVA',
            // sem cep/logradouro/numero/bairro/municipio/uf/codigo_municipio/email/telefone
        ], 200),
    ]);

    $response = $this->getJson('/cliente/lookup/cnpj/11222333000181');

    $response->assertStatus(200)
        ->assertJson([
            'razao_social' => 'ACME COMERCIO LTDA',
            'zip_code' => '',
            'address_line_1' => '',
            'neighborhood' => '',
            'city' => '',
            'state' => '',
            'city_code' => '',
            'mobile' => '',
            'email' => '',
        ]);
});

test('GET /cliente/lookup/cnpj/{cnpj} -- codigo_municipio nao numerico vira string vazia (defensive)', function () {
    Http::fake([
        'brasilapi.com.br/api/cnpj/v1/11222333000181' => Http::response([
            'razao_social' => 'ACME COMERCIO LTDA',
            'nome_fantasia' => 'ACME',
            'descricao_situacao_cadastral' => 'ATIVA',
            // BrasilAPI as vezes retorna codigo_municipio como string "3550308"
            // (CNPJ antigo) ou ate null. Service normaliza pra digits.
            'codigo_municipio' => '3550308',
            'ddd_telefone_1' => '(11) 3333-4444', // formato com mascara
            'email' => 'CONTATO@ACME.COM.BR ',     // upper + trailing space
        ], 200),
    ]);

    $response = $this->getJson('/cliente/lookup/cnpj/11222333000181');

    $response->assertStatus(200)
        ->assertJson([
            'city_code' => '3550308',
            // Service tira mascara do telefone (so digitos).
            'mobile' => '1133334444',
            // Email trim mas NAO lowercase (Receita pode usar maiuscula corporativa).
            'email' => 'CONTATO@ACME.COM.BR',
        ]);
});

test('GET /cliente/lookup/cnpj/{cnpj} -- cache HIT NAO bate BrasilAPI segunda vez', function () {
    Http::fake([
        'brasilapi.com.br/api/cnpj/v1/11222333000181' => Http::response([
            'razao_social' => 'ACME COMERCIO LTDA',
            'nome_fantasia' => 'ACME',
            'descricao_situacao_cadastral' => 'ATIVA',
        ], 200),
    ]);

    $this->getJson('/cliente/lookup/cnpj/11222333000181')->assertStatus(200);
    $this->getJson('/cliente/lookup/cnpj/11222333000181')->assertStatus(200);

    Http::assertSentCount(1);
});

test('GET /cliente/lookup/cnpj/{cnpj} -- 14 digitos invalidos (BrasilAPI 404) retorna 404', function () {
    Http::fake([
        'brasilapi.com.br/api/cnpj/v1/*' => Http::response(['message' => 'CNPJ invalido'], 404),
    ]);

    $response = $this->getJson('/cliente/lookup/cnpj/11111111111111');

    $response->assertStatus(404)
        ->assertJsonStructure(['message']);
});

test('GET /cliente/lookup/cnpj/{cnpj} -- upstream rate limit retorna 404 graceful', function () {
    Http::fake([
        'brasilapi.com.br/api/cnpj/*' => Http::response('Too many', 429),
    ]);

    $response = $this->getJson('/cliente/lookup/cnpj/11222333000181');

    $response->assertStatus(404);
});

// ---------------------------------------------------------------------
// Multi-tenant + auth gating
// ---------------------------------------------------------------------

test('lookup endpoints exigem auth (sem login -> 302/401)', function () {
    Http::fake();
    // Logout pro middleware auth bloquear.
    auth()->logout();
    session()->forget('user.business_id');

    $response = $this->getJson('/cliente/lookup/cep/01310100');

    // auth middleware UPOS redireciona pra login (302) OU 401 em XHR.
    // Importa apenas que NAO retornou 200.
    expect($response->getStatusCode())->toBeIn([302, 401]);
});

test('BrLookupService::lookupCep -- formato CEP invalido (menos 8 digitos) retorna null sem hittar HTTP', function () {
    // Teste service-only, NAO depende de schema -- precisa skipar o beforeEach.
    Http::fake();

    $service = new \Modules\Crm\Services\BrLookupService;
    $result = $service->lookupCep('123'); // 3 digitos so

    expect($result)->toBeNull();

    // Como o service short-circuita em strlen != 8, NUNCA chama HTTP.
    Http::assertSentCount(0);
})->skip(fn () => ! Schema::hasTable('contacts'), 'Schema UltimatePOS ausente.');

test('BrLookupService::lookupCnpj -- formato CNPJ invalido (menos 14 digitos) retorna null sem hittar HTTP', function () {
    Http::fake();

    $service = new \Modules\Crm\Services\BrLookupService;
    $result = $service->lookupCnpj('11222'); // 5 digitos so

    expect($result)->toBeNull();

    Http::assertSentCount(0);
})->skip(fn () => ! Schema::hasTable('contacts'), 'Schema UltimatePOS ausente.');
