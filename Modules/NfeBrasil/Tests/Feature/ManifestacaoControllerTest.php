<?php

declare(strict_types=1);

// @covers-us US-NFE-008 — endpoints de manifestação (confirmar/desconhecer/bulk) + guard cross-tenant 404.

use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeDfeRecebido;

uses(Tests\TestCase::class);

/**
 * US-NFE-052 — ManifestacaoController.
 *
 * Tests focados em validação de input + auth + cross-tenant guard.
 * Lógica SEFAZ é mockada via ManifestacaoService.
 */

function manifestacaoCtrlBootstrap(): array
{
    if (! Schema::hasTable('nfe_dfe_recebidos') || ! Schema::hasTable('nfe_dfe_eventos')) {
        test()->markTestSkipped('Tabelas nfe_dfe_* ausentes — rode migrations 2026_05_09_100000+ primeiro.');
    }

    try {
        $business = \App\Business::first();
        $user = \App\User::whereHas('roles', fn ($q) => $q->where('name', 'like', 'Admin%'))->first()
            ?? \App\User::first();
    } catch (\Throwable) {
        test()->markTestSkipped('Tabelas core indisponíveis.');
    }

    if (! $business || ! $user) {
        test()->markTestSkipped('Sem business/user no banco.');
    }

    return [$business, $user];
}

function actingAsUserComBiz($user, $businessId): void
{
    test()->actingAs($user);
    session([
        'user.id' => $user->id,
        'user.business_id' => $businessId,
        'business.id' => $businessId,
    ]);
}

afterEach(function () {
    \Mockery::close();
});

it('GET /nfe-brasil/manifestacao requer auth', function () {
    [$business] = manifestacaoCtrlBootstrap();

    $response = test()->get('/nfe-brasil/manifestacao');

    expect($response->status())->toBeIn([302, 401]); // redirect login ou 401
});

it('POST /confirmar exige justificativa null (sem param) e cross-tenant guard 404', function () {
    [$business, $user] = manifestacaoCtrlBootstrap();
    actingAsUserComBiz($user, (int) $business->id);

    // ID inexistente OU de outro business — deve retornar 404
    $response = test()->post('/nfe-brasil/manifestacao/999999999/confirmar');
    expect($response->status())->toBe(404);
});

it('POST /desconhecer rejeita justificativa <15 chars', function () {
    [$business, $user] = manifestacaoCtrlBootstrap();
    actingAsUserComBiz($user, (int) $business->id);

    $dfe = NfeDfeRecebido::create([
        'business_id'         => (int) $business->id,
        'chave_44'            => '35210112345678000199550010000999999000000099',
        'nsu'                 => 99999,
        'cnpj_emitente'       => '12345678000199',
        'nome_emitente'       => 'TEST FORNECEDOR',
        'valor_total'         => 100,
        'data_emissao'        => now(),
        'status_manifestacao' => 'pendente',
    ]);

    $response = test()->post("/nfe-brasil/manifestacao/{$dfe->id}/desconhecer", [
        'justificativa' => 'curta',
    ]);

    expect($response->status())->toBeIn([302, 422]); // validation
    $dfe->delete();
});

it('POST /bulk/confirmar com IDs vazios retorna error flash', function () {
    [$business, $user] = manifestacaoCtrlBootstrap();
    actingAsUserComBiz($user, (int) $business->id);

    $response = test()->post('/nfe-brasil/manifestacao/bulk/confirmar', ['ids' => []]);

    expect($response->status())->toBe(302); // redirect back
});

it('GET /{id}/itens retorna JSON com chave itens (mesmo lista vazia)', function () {
    [$business, $user] = manifestacaoCtrlBootstrap();
    actingAsUserComBiz($user, (int) $business->id);

    $dfe = NfeDfeRecebido::create([
        'business_id'         => (int) $business->id,
        'chave_44'            => '35210112345678000199550010000888888000000088',
        'nsu'                 => 88888,
        'cnpj_emitente'       => '12345678000199',
        'nome_emitente'       => 'TEST',
        'valor_total'         => 50,
        'data_emissao'        => now(),
        'status_manifestacao' => 'pendente',
    ]);

    $response = test()->get("/nfe-brasil/manifestacao/{$dfe->id}/itens");

    expect($response->status())->toBe(200);
    expect($response->json())->toHaveKey('itens');
    expect($response->json('itens'))->toBeArray();

    $dfe->delete();
});

it('GET /{id}/eventos retorna JSON com chave eventos', function () {
    [$business, $user] = manifestacaoCtrlBootstrap();
    actingAsUserComBiz($user, (int) $business->id);

    $dfe = NfeDfeRecebido::create([
        'business_id'         => (int) $business->id,
        'chave_44'            => '35210112345678000199550010000777777000000077',
        'nsu'                 => 77777,
        'cnpj_emitente'       => '12345678000199',
        'nome_emitente'       => 'TEST',
        'valor_total'         => 50,
        'data_emissao'        => now(),
        'status_manifestacao' => 'pendente',
    ]);

    $response = test()->get("/nfe-brasil/manifestacao/{$dfe->id}/eventos");

    expect($response->status())->toBe(200);
    expect($response->json())->toHaveKey('eventos');

    $dfe->delete();
});

it('cross-tenant: tenta acessar dfe de outro business retorna 404', function () {
    [$business, $user] = manifestacaoCtrlBootstrap();
    $outroBusiness = (int) $business->id + 99999;
    actingAsUserComBiz($user, (int) $business->id);

    // Cria DFe num business completamente diferente, sem scope global tester
    $dfe = NfeDfeRecebido::create([
        'business_id'         => $outroBusiness,
        'chave_44'            => '35210112345678000199550010000666666000000066',
        'nsu'                 => 66666,
        'cnpj_emitente'       => '12345678000199',
        'nome_emitente'       => 'OUTRO TENANT',
        'valor_total'         => 50,
        'data_emissao'        => now(),
        'status_manifestacao' => 'pendente',
    ]);

    $response = test()->get("/nfe-brasil/manifestacao/{$dfe->id}/itens");

    expect($response->status())->toBe(404);

    NfeDfeRecebido::withoutGlobalScopes()->where('id', $dfe->id)->delete();
});
