<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeEmissao;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\DatabaseTransactions::class);

/**
 * US-NFE-002 fase 2C · Endpoint JSON polling-friendly de status NFC-e.
 *
 * Tests cobrem:
 *   1. Sem session('business.id') → 400 no_business_context
 *   2. Sem emissão pra a tx → 200 com status:null + message
 *   3. Cross-tenant: emissao em outro business_id → tratada como inexistente (200, status:null)
 *   4. Modelo 55 (NFe) → ignorada (controller só busca modelo 65)
 *   5. Status pendente → is_terminal=false
 *   6. Status autorizada → payload completo + is_terminal=true
 *   7. Status rejeitada → is_terminal=true
 *   8. Múltiplas emissões (retentativas) → retorna a mais recente (orderByDesc id)
 */

beforeEach(function () {
    if (! Schema::hasTable('nfe_emissoes')) {
        $this->markTestSkipped('nfe_emissoes não existe — migration não rodou');
    }
});

/**
 * Helper: chama endpoint simulando session com business.id.
 */
function callNfeStatus(int $txId, ?int $businessId = 1)
{
    $session = $businessId ? ['business.id' => $businessId] : [];
    return test()->withSession($session)->getJson("/nfe-brasil/api/transactions/{$txId}/nfe-status");
}

it('sem business.id na session → 400', function () {
    $response = callNfeStatus(123, businessId: null);

    $response->assertStatus(400)
        ->assertJson(['error' => 'no_business_context']);
});

it('sem emissão pra tx → 200 com status:null e message', function () {
    $response = callNfeStatus(7777777);

    $response->assertOk()
        ->assertJson([
            'transaction_id' => 7777777,
            'status'         => null,
        ])
        ->assertJsonStructure(['transaction_id', 'status', 'message']);
});

it('cross-tenant: emissão em outro business → tratada como inexistente', function () {
    NfeEmissao::create([
        'business_id'    => 99, // ← outro business
        'transaction_id' => 7000001,
        'modelo'         => 65,
        'serie'          => '1',
        'numero'         => 1,
        'status'         => 'autorizada',
        'cstat'          => '100',
        'valor_total'    => 100.00,
    ]);

    $response = callNfeStatus(7000001, businessId: 1); // ← business 1 logado (Wagner)

    $response->assertOk()
        ->assertJson([
            'transaction_id' => 7000001,
            'status'         => null,
        ]);
});

it('modelo 55 (NFe) é ignorado pelo endpoint NFC-e', function () {
    NfeEmissao::create([
        'business_id'    => 1,
        'transaction_id' => 7000002,
        'modelo'         => 55, // ← NFe normal, não NFC-e
        'serie'          => '1',
        'numero'         => 1,
        'status'         => 'autorizada',
        'cstat'          => '100',
        'valor_total'    => 100.00,
    ]);

    $response = callNfeStatus(7000002);

    $response->assertOk()
        ->assertJson(['transaction_id' => 7000002, 'status' => null]);
});

it('status pendente → is_terminal=false', function () {
    NfeEmissao::create([
        'business_id'    => 1,
        'transaction_id' => 7000003,
        'modelo'         => 65,
        'serie'          => '1',
        'numero'         => 1,
        'status'         => 'pendente',
        'valor_total'    => 100.00,
    ]);

    $response = callNfeStatus(7000003);

    $response->assertOk()
        ->assertJson([
            'transaction_id' => 7000003,
            'status'         => 'pendente',
            'is_terminal'    => false,
        ]);
});

it('status autorizada → payload completo + is_terminal=true', function () {
    $emissao = NfeEmissao::create([
        'business_id'    => 1,
        'transaction_id' => 7000004,
        'modelo'         => 65,
        'serie'          => '1',
        'numero'         => 42,
        'status'         => 'autorizada',
        'cstat'          => '100',
        'chave_44'       => '35210112345678000199650010000000421000000049',
        'valor_total'    => 250.50,
        'emitido_em'     => now(),
    ]);

    $response = callNfeStatus(7000004);

    $response->assertOk()
        ->assertJson([
            'transaction_id' => 7000004,
            'emissao_id'     => $emissao->id,
            'status'         => 'autorizada',
            'modelo'         => '65',
            'cstat'          => '100',
            'chave_44'       => '35210112345678000199650010000000421000000049',
            'numero'         => 42,
            'serie'          => '1',
            'valor_total'    => 250.50,
            'is_terminal'    => true,
        ]);
});

it('status rejeitada → is_terminal=true', function () {
    NfeEmissao::create([
        'business_id'    => 1,
        'transaction_id' => 7000005,
        'modelo'         => 65,
        'serie'          => '1',
        'numero'         => 1,
        'status'         => 'rejeitada',
        'cstat'          => '215',
        'motivo'         => 'NCM inválido',
        'valor_total'    => 100.00,
    ]);

    $response = callNfeStatus(7000005);

    $response->assertOk()
        ->assertJson([
            'status'      => 'rejeitada',
            'cstat'       => '215',
            'motivo'      => 'NCM inválido',
            'is_terminal' => true,
        ]);
});

it('múltiplas emissões pra mesma tx → retorna a mais recente', function () {
    // Cenário: primeira emissão rejeitada, segunda re-tentou e autorizou.
    NfeEmissao::create([
        'business_id'    => 1,
        'transaction_id' => 7000006,
        'modelo'         => 65,
        'serie'          => '1',
        'numero'         => 100,
        'status'         => 'rejeitada',
        'cstat'          => '215',
        'valor_total'    => 100.00,
    ]);

    $segunda = NfeEmissao::create([
        'business_id'    => 1,
        'transaction_id' => 7000006,
        'modelo'         => 65,
        'serie'          => '1',
        'numero'         => 101,
        'status'         => 'autorizada',
        'cstat'          => '100',
        'valor_total'    => 100.00,
    ]);

    $response = callNfeStatus(7000006);

    $response->assertOk()
        ->assertJson([
            'emissao_id' => $segunda->id,
            'status'     => 'autorizada',
            'numero'     => 101,
        ]);
});
