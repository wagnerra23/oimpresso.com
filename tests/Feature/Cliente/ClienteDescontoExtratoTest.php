<?php

declare(strict_types=1);
// Cobre UC-CIDX-18 (Index.casos.md) - G-2 rastreabilidade caso-teste.

use App\Utils\ContactUtil;
use App\Utils\Util;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Desconto no extrato lançado pela ficha do cliente (POST /ledger-discount).
 *
 * Achado de 2026-11-16 (pedido do Felipe): o desconto lançado pela ficha NUNCA gravava — a tela
 * mandava só a data (`2026-11-17`) e o servidor exigia data com hora no formato da empresa; a
 * exceção virava {success: 0}. E criar/editar desconto não conferia permissão no servidor.
 *
 * Os testes mandam o que a ficha manda agora: data ISO com hora e valor com 2 casas (`25.00`).
 * A empresa de teste usa `d/m/Y`, como as empresas reais, para o caminho ISO ser exercitado
 * de verdade (com `Y-m-d` o formato antigo também passaria e o teste não morderia).
 *
 * Tenant de teste 98 × 99 (ADR 0358). Nomes fictícios.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('transactions')) {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS (ADR 0358).');
    }
    $this->business = $this->seededTenant();
});

function cdeEntrar($teste, array $permissoes): \App\User
{
    $user = $teste->usuarioComPermissoes($permissoes, $teste->business);
    $teste->actingAs($user)->withSession([
        'user.business_id' => $teste->business->id, 'business.id' => $teste->business->id, 'user.id' => $user->id,
        'business.date_format' => 'd/m/Y', 'business.time_format' => 24,
    ]);

    return $user;
}

function cdeContato(int $businessId, int $criador): int
{
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => $businessId, 'type' => 'customer', 'name' => 'Cliente Desconto '.uniqid(),
        'contact_status' => 'active', 'created_by' => $criador, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function cdeDescontos(int $contato)
{
    return DB::table('transactions')->where('type', 'ledger_discount')->where('contact_id', $contato);
}

test('UC-CIDX-18 sem discount.manage, lançar desconto responde 403 e nada é gravado', function () {
    $user = cdeEntrar($this, ['customer.view']);
    $id = cdeContato($this->business->id, $user->id);

    $this->postJson('/ledger-discount', [
        'contact_id' => $id, 'date' => '2026-11-17 14:30', 'amount' => '25.00',
    ])->assertForbidden();

    expect(cdeDescontos($id)->exists())->toBeFalse();
});

test('UC-CIDX-18 o desconto da ficha (data ISO com hora, valor 25.00) grava 25,00 na data escolhida', function () {
    $user = cdeEntrar($this, ['customer.view', 'discount.manage']);
    $id = cdeContato($this->business->id, $user->id);

    $this->postJson('/ledger-discount', [
        'contact_id' => $id, 'date' => '2026-11-17 14:30', 'amount' => '25.00', 'note' => 'acerto',
    ])->assertOk()->assertJsonPath('success', true);

    $linha = cdeDescontos($id)->first();
    expect($linha)->not->toBeNull();
    // Caminho 1: a linha gravada.
    expect((float) $linha->final_total)->toBe(25.0);
    expect((float) $linha->total_before_tax)->toBe(25.0);
    expect($linha->transaction_date)->toBe('2026-11-17 14:30:00');
    expect($linha->sub_type)->toBe('sell_discount');
    expect((int) $linha->business_id)->toBe((int) $this->business->id);

    // Caminho 2: a soma que o extrato e os saldos usam (ContactUtil) lê o mesmo 25,00.
    $soma = app(ContactUtil::class)->getContactQuery($this->business->id, 'customer', [$id])->first();
    expect((float) $soma->total_ledger_discount)->toBe(25.0);
});

test('UC-CIDX-18 o conversor do servidor lê o valor da ficha sem ambiguidade', function () {
    // A ficha manda sempre 2 casas. Com 3 casas o mesmo número seria lido como milhar.
    $util = app(Util::class);
    expect($util->num_uf('25.00'))->toBe(25.0)
        ->and($util->num_uf('1234.50'))->toBe(1234.5)
        ->and($util->num_uf('0.10'))->toBe(0.1)
        ->and($util->num_uf('25.000'))->toBe(25000.0);
});

test('UC-CIDX-18 valor zero é recusado e nada é gravado', function () {
    $user = cdeEntrar($this, ['customer.view', 'discount.manage']);
    $id = cdeContato($this->business->id, $user->id);

    $this->postJson('/ledger-discount', [
        'contact_id' => $id, 'date' => '2026-11-17 14:30', 'amount' => '0',
    ])->assertOk()->assertJsonPath('success', 0);

    expect(cdeDescontos($id)->exists())->toBeFalse();
});

test('UC-CIDX-18 a tela antiga (data no formato da empresa) continua gravando', function () {
    $user = cdeEntrar($this, ['customer.view', 'discount.manage']);
    $id = cdeContato($this->business->id, $user->id);

    $this->postJson('/ledger-discount', [
        'contact_id' => $id, 'date' => '17/11/2026 09:05', 'amount' => '10,50',
    ])->assertOk()->assertJsonPath('success', true);

    $linha = cdeDescontos($id)->first();
    expect((float) $linha->final_total)->toBe(10.5);
    expect($linha->transaction_date)->toBe('2026-11-17 09:05:00');
});

test('UC-CIDX-18 sem discount.manage, editar desconto responde 403 e o valor não muda', function () {
    $dono = cdeEntrar($this, ['customer.view', 'discount.manage']);
    $id = cdeContato($this->business->id, $dono->id);
    $this->postJson('/ledger-discount', ['contact_id' => $id, 'date' => '2026-11-17 14:30', 'amount' => '25.00'])
        ->assertJsonPath('success', true);
    $descontoId = (int) cdeDescontos($id)->value('id');

    cdeEntrar($this, ['customer.view']);
    $this->putJson("/ledger-discount/{$descontoId}", [
        'date' => '2026-11-17 14:30', 'amount' => '1.00',
    ])->assertForbidden();

    expect((float) cdeDescontos($id)->value('final_total'))->toBe(25.0);
});

test('UC-CIDX-18 Tier 0 — com discount.manage, desconto para cliente de outra empresa não grava', function () {
    $user = cdeEntrar($this, ['customer.view', 'discount.manage']);
    $outra = $this->seededSupportClientTenant();
    $alheio = cdeContato((int) $outra->id, $user->id);

    $this->postJson('/ledger-discount', [
        'contact_id' => $alheio, 'date' => '2026-11-17 14:30', 'amount' => '25.00',
    ])->assertOk()->assertJsonPath('success', 0);

    expect(cdeDescontos($alheio)->exists())->toBeFalse();
});
