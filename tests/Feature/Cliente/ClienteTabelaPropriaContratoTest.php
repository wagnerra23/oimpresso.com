<?php

declare(strict_types=1);
// Cobre UC-CIDX-06, UC-CIDX-07, UC-CIDX-08 (Cliente/Index.casos.md · pedido P-087).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P-087 — a tabela de preço pode ficar no próprio cliente, além do grupo.
 *
 * Medido no Delphi do Sabor Brasil em 2026-10-29: 87 dos 192 clientes com tabela também têm
 * um grupo, então o grupo sozinho não cobre (uma coluna, dois vínculos). A venda lê a tabela
 * em `GET /contacts/customers` (o mesmo endpoint da busca de cliente da tela de venda), e a
 * tela reprecifica pelo `selling_price_group_id` que vem dali.
 * Tenant de teste 98 × adversário 99 (ADR 0358).
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('selling_price_groups') || ! Schema::hasColumn('contacts', 'selling_price_group_id')) {
        $this->markTestSkipped('Schema UltimatePOS ausente ou migration P-087 não rodou — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['customer.view', 'customer.update'], $this->business);
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
});

function tpTabela(int $businessId, string $nome): int
{
    return (int) DB::table('selling_price_groups')->insertGetId([
        'name' => $nome.' '.uniqid(), 'business_id' => $businessId,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function tpCliente(int $businessId, int $userId, ?int $grupo, ?int $tabelaPropria): int
{
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => $businessId, 'type' => 'customer', 'name' => 'Cliente P087 '.uniqid(),
        'customer_group_id' => $grupo, 'selling_price_group_id' => $tabelaPropria,
        'created_by' => $userId, 'contact_status' => 'active',
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function tpGrupo(int $businessId, int $userId, int $tabela): int
{
    return (int) DB::table('customer_groups')->insertGetId([
        'business_id' => $businessId, 'name' => 'Grupo P087 '.uniqid(), 'amount' => 0,
        'price_calculation_type' => 'selling_price_group', 'selling_price_group_id' => $tabela,
        'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Tabela que a busca de cliente da venda devolve para o contato. */
function tpTabelaNaVenda($teste, int $contactId): mixed
{
    $r = $teste->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->getJson('/contacts/customers');
    $r->assertOk();
    $linha = collect($r->json())->firstWhere('id', $contactId);
    expect($linha)->not->toBeNull('o cliente do teste não voltou na busca da venda');

    return $linha['selling_price_group_id'];
}

test('UC-CIDX-06 a tabela própria do cliente vale no lugar da do grupo na venda', function () {
    $atacado = tpTabela($this->business->id, 'Atacado');
    $redeX = tpTabela($this->business->id, 'Rede X');
    $grupo = tpGrupo($this->business->id, $this->user->id, $atacado);

    $comPropria = tpCliente($this->business->id, $this->user->id, $grupo, $redeX);
    $soGrupo = tpCliente($this->business->id, $this->user->id, $grupo, null);
    $semNada = tpCliente($this->business->id, $this->user->id, null, null);

    expect((int) tpTabelaNaVenda($this, $comPropria))->toBe($redeX)
        ->and((int) tpTabelaNaVenda($this, $soGrupo))->toBe($atacado)
        ->and(tpTabelaNaVenda($this, $semNada))->toBeNull();
});

test('UC-CIDX-07 o drawer grava e devolve a tabela própria; vazio volta a valer o grupo', function () {
    $redeX = tpTabela($this->business->id, 'Rede X');
    $cliente = tpCliente($this->business->id, $this->user->id, null, null);

    $r = $this->patchJson("/cliente/{$cliente}/comercial", ['selling_price_group_id' => $redeX]);
    $r->assertOk();
    expect($r->json('contact.selling_price_group_id'))->toBe($redeX)
        ->and((int) DB::table('contacts')->where('id', $cliente)->value('selling_price_group_id'))->toBe($redeX);

    $this->patchJson("/cliente/{$cliente}/comercial", ['selling_price_group_id' => null])->assertOk();
    expect(DB::table('contacts')->where('id', $cliente)->value('selling_price_group_id'))->toBeNull();
});

test('UC-CIDX-08 Tier 0 — tabela de outra empresa é recusada e não vira preço', function () {
    $outro = $this->seededSupportClientTenant();
    $alheia = tpTabela($outro->id, 'Alheia');
    $cliente = tpCliente($this->business->id, $this->user->id, null, null);

    $this->patchJson("/cliente/{$cliente}/comercial", ['selling_price_group_id' => $alheia])
        ->assertStatus(422)
        ->assertJsonPath('errors.selling_price_group_id.0', fn ($m) => is_string($m) && $m !== '');
    expect(DB::table('contacts')->where('id', $cliente)->value('selling_price_group_id'))->toBeNull();

    // Gravado por fora (import, SQL), o id alheio não chega à venda: o join exige a mesma empresa.
    DB::table('contacts')->where('id', $cliente)->update(['selling_price_group_id' => $alheia]);
    expect(tpTabelaNaVenda($this, $cliente))->toBeNull();
});
