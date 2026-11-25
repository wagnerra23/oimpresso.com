<?php

declare(strict_types=1);
// Cobre UC-CIDX-25 (Index.casos.md) — Operações › Pessoas da gaveta carrega as pessoas de contato.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A sub-aba Operações › Pessoas da gaveta passava `contact_persons={undefined}` e ficava em
 * "Carregando pessoas de contato…" para sempre (medição 2026-11-25, A-02). Agora ela busca
 * GET /cliente/{id}/pessoas-json, que lista os usuários CRM ligados ao cliente.
 *
 * Tenant de teste (ADR 0358). Transação revertida por caso.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasColumn('users', 'crm_contact_id')) {
        $this->markTestSkipped('Schema UltimatePOS + CRM exigem MySQL (ADR 0358).');
    }

    $this->business = $this->seededTenant();
    $biz = (int) $this->business->id;

    $this->operador = $this->usuarioComPermissoes(['customer.view'], $this->business);

    $novo = function (int $bizId, int $criadoPor): int {
        $linha = [
            'business_id' => $bizId, 'type' => 'customer', 'name' => 'CPJ-' . uniqid(),
            'mobile' => '00000000000', 'contact_status' => 'active',
            'created_by' => $criadoPor, 'created_at' => now(), 'updated_at' => now(),
        ];
        if (Schema::hasColumn('contacts', 'is_customer')) {
            $linha['is_customer'] = 1;
        }

        return (int) DB::table('contacts')->insertGetId($linha);
    };
    $this->comPessoa = $novo($biz, (int) $this->operador->id);
    $this->semPessoa = $novo($biz, (int) $this->operador->id);

    // A pessoa de contato é um usuário CRM da mesma empresa com crm_contact_id = cliente.
    $this->pessoa = \App\User::factory()->create([
        'business_id' => $biz,
        'first_name' => 'Pessoa',
        'last_name' => 'Do Cliente',
        'crm_contact_id' => $this->comPessoa,
    ]);
});

function cpjEntrar($test, $user): void
{
    $test->actingAs($user);
    session([
        'user.business_id' => $test->business->id,
        'user.id' => $user->id,
        'business.id' => $test->business->id,
    ]);
}

test('UC-CIDX-25 lista as pessoas de contato do cliente', function () {
    cpjEntrar($this, $this->operador);

    $r = $this->getJson("/cliente/{$this->comPessoa}/pessoas-json");

    $r->assertOk();
    $ids = collect($r->json())->pluck('id')->all();
    expect($ids)->toBe([(int) $this->pessoa->id]);
    expect($r->json('0.full_name'))->toContain('Pessoa');
});

test('UC-CIDX-25 cliente sem pessoa devolve lista vazia (estado vazio da aba)', function () {
    cpjEntrar($this, $this->operador);

    $r = $this->getJson("/cliente/{$this->semPessoa}/pessoas-json");

    $r->assertOk();
    expect($r->json())->toBe([]);
});

test('UC-CIDX-25 cliente de outra empresa responde 404', function () {
    $outra = $this->seededSupportClientTenant();
    $alheio = (int) DB::table('contacts')->insertGetId([
        'business_id' => $outra->id, 'type' => 'customer', 'name' => 'CPJ-alheio-' . uniqid(),
        'mobile' => '00000000000', 'contact_status' => 'active',
        'created_by' => $outra->owner_id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    cpjEntrar($this, $this->operador);

    expect($this->getJson("/cliente/{$alheio}/pessoas-json")->status())->toBe(404);
    // Controle do lado aceito: o cliente da própria empresa abre.
    expect($this->getJson("/cliente/{$this->comPessoa}/pessoas-json")->status())->toBe(200);
});

test('UC-CIDX-25 só-próprios não lê as pessoas do cliente de outro vendedor', function () {
    $vendedor = $this->usuarioComPermissoes(['customer.view_own'], $this->business);
    cpjEntrar($this, $vendedor);

    expect($this->getJson("/cliente/{$this->comPessoa}/pessoas-json")->status())->toBe(403);
});
