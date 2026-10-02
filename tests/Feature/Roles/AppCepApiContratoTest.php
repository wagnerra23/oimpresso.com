<?php

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php. NÃO redeclarar aqui.

use App\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;

/**
 * Busca de CEP do app das lojas (tela 09, botão "Buscar") — GET /api/app/cep/{cep}.
 *
 * Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §4.3. O app não chama o ViaCEP
 * direto: o ERP responde pelo proxy com cache do Crm. ViaCEP falso (Http::fake), nada sai pra
 * rede. NÃO derivado do controller.
 *
 * CEP é dado público: sem business_id. O usuário é do tenant fictício 98 (ADR 0358).
 */

beforeEach(function () {
    // O guard `api` do Passport precisa das chaves mesmo com Passport::actingAs; a lane não as gera.
    if (! file_exists(storage_path('oauth-public.key'))) {
        \Illuminate\Support\Facades\Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema UltimatePOS exige MySQL (ADR 0358).');
    }
    if (! DB::table('business')->where('id', 98)->exists()) {
        $this->markTestSkipped('Tenant 98 ausente nesta lane.');
    }
    if (! app()->bound(\Modules\Crm\Services\BrLookupService::class) && ! class_exists(\Modules\Crm\Services\BrLookupService::class)) {
        $this->markTestSkipped('Módulo Crm ausente.');
    }
    Cache::flush();
    DB::beginTransaction();
});

function appCepLogar(): void
{
    Passport::actingAs(User::factory()->create(['business_id' => 98]), [], 'api');
}

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

it('sem token responde 401', function () {
    $this->getJson('/api/app/cep/88015000')->assertStatus(401);
});

it('CEP achado devolve o endereço com código IBGE; a 2ª busca sai do cache', function () {
    appCepLogar();
    Http::fake(['viacep.com.br/*' => Http::response([
        'cep' => '88015-000', 'logradouro' => 'Rua Teste', 'complemento' => '', 'bairro' => 'Centro',
        'localidade' => 'Florianópolis', 'uf' => 'SC', 'ibge' => '4205407',
    ])]);

    $this->getJson('/api/app/cep/88015-000')->assertOk()->assertExactJson([
        'cep' => '88015000', 'logradouro' => 'Rua Teste', 'complemento' => '', 'bairro' => 'Centro',
        'cidade' => 'Florianópolis', 'uf' => 'SC', 'codigo_ibge' => '4205407',
    ]);
    $this->getJson('/api/app/cep/88015000')->assertOk();

    Http::assertSentCount(1);
});

it('CEP inexistente responde 404 nao_encontrado', function () {
    appCepLogar();
    Http::fake(['viacep.com.br/*' => Http::response(['erro' => true])]);

    $this->getJson('/api/app/cep/99999999')->assertStatus(404)->assertJsonPath('erro', 'nao_encontrado');
});

it('CEP com tamanho errado responde 422 sem chamar o ViaCEP', function () {
    appCepLogar();
    Http::fake();

    $this->getJson('/api/app/cep/8801500')->assertStatus(422)->assertJsonPath('erro', 'validacao');
    Http::assertNothingSent();
});
