<?php

declare(strict_types=1);
// Cobre UC-CIDX-15 (Index.casos.md) - G-2 rastreabilidade caso-teste.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Excluir cadastro de pessoa (`DELETE /contacts/{id}` → ContactController::destroy), o mesmo
 * caminho da lista de clientes e da ficha completa.
 *
 * Excluir não apaga a linha: marca `deleted_at`, e o cadastro fica para consulta. Regras de
 * 2026-11-16 (pedido do Felipe):
 *  - só com "Remover cliente/fornecedor" — antes "Ver os próprios" também passava no servidor;
 *  - cadastro com qualquer registro de negócio (venda, OS, assinatura, cobrança, veículo, crédito…)
 *    não é excluído — antes só se contavam os lançamentos;
 *  - o cliente padrão é recusado com mensagem — antes respondia "excluído" sem excluir;
 *  - cadastro de outra empresa responde 404.
 *
 * Tenant de teste 98 × 99 (ADR 0358). Nomes e placas fictícios.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('contacts')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->novoContato = function (int $businessId, int $createdBy, array $extra = []): int {
        return (int) DB::table('contacts')->insertGetId($extra + [
            'business_id' => $businessId, 'created_by' => $createdBy, 'type' => 'customer',
            'name' => 'Cliente Excluir '.uniqid(), 'contact_status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    };
});

function cerEntrar($teste, array $permissoes): \App\User
{
    $user = $teste->usuarioComPermissoes($permissoes, $teste->business);
    $teste->actingAs($user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $user->id, 'business.id' => $teste->business->id]);

    return $user;
}

/** DELETE como a lista de clientes manda (XHR + JSON). */
function cerExcluir($teste, int $id)
{
    return $teste->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
        ->delete("/contacts/{$id}");
}

function cerExcluido(int $id): bool
{
    return DB::table('contacts')->where('id', $id)->whereNotNull('deleted_at')->exists();
}

test('UC-CIDX-15 com "Remover cliente", cadastro sem vínculo é marcado como excluído e a linha fica no banco', function () {
    $user = cerEntrar($this, ['customer.view', 'customer.delete']);
    $id = ($this->novoContato)($this->business->id, $user->id);

    cerExcluir($this, $id)->assertOk()->assertJsonPath('success', true);

    expect(DB::table('contacts')->where('id', $id)->exists())->toBeTrue();
    expect(cerExcluido($id))->toBeTrue();
});

test('UC-CIDX-15 só "Ver os próprios clientes" não exclui (403), mesmo o cadastro que a pessoa criou', function () {
    $user = cerEntrar($this, ['customer.view_own']);
    $id = ($this->novoContato)($this->business->id, $user->id);

    cerExcluir($this, $id)->assertForbidden();

    expect(cerExcluido($id))->toBeFalse();
});

test('UC-CIDX-15 cadastro com veículo (sem nenhuma venda) não é excluído e a mensagem diz por quê', function () {
    if (! Schema::hasTable('vehicles')) {
        $this->markTestSkipped('Tabela vehicles ausente nesta lane.');
    }
    $user = cerEntrar($this, ['customer.view', 'customer.delete']);
    $id = ($this->novoContato)($this->business->id, $user->id);
    DB::table('vehicles')->insert([
        'business_id' => $this->business->id, 'contact_id' => $id, 'plate' => 'ZQX'.random_int(1000, 9999),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    // Controle: o cadastro não tem lançamento nenhum — a regra antiga o excluiria.
    expect(DB::table('transactions')->where('contact_id', $id)->exists())->toBeFalse();

    $r = cerExcluir($this, $id);

    $r->assertOk()->assertJsonPath('success', false);
    expect((string) $r->json('msg'))->toContain('veículos');
    expect(cerExcluido($id))->toBeFalse();
});

test('UC-CIDX-15 o cliente padrão é recusado com mensagem, não "excluído"', function () {
    $user = cerEntrar($this, ['customer.view', 'customer.delete']);
    $id = ($this->novoContato)($this->business->id, $user->id, ['is_default' => 1]);

    $r = cerExcluir($this, $id);

    $r->assertOk()->assertJsonPath('success', false);
    expect((string) $r->json('msg'))->toContain('cliente padrão');
    expect(cerExcluido($id))->toBeFalse();
});

test('UC-CIDX-15 Tier 0 — cadastro de outra empresa responde 404 e fica como está', function () {
    $user = cerEntrar($this, ['customer.view', 'customer.delete']);
    $outra = $this->seededSupportClientTenant();
    $alheio = ($this->novoContato)((int) $outra->id, $user->id);

    cerExcluir($this, $alheio)->assertNotFound();

    expect(cerExcluido($alheio))->toBeFalse();
});
