<?php

declare(strict_types=1);
// Cobre UC-CIDX-16 (Index.casos.md) - G-2 rastreabilidade caso-teste.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Filtro "Excluídos" da lista de clientes e Restaurar (pedido do Felipe, 2026-11-16: excluir
 * marca o cadastro como excluído para eventuais consultas).
 *
 * Excluir já era soft delete (deleted_at), mas nenhuma tela mostrava os excluídos. Agora
 * `/cliente?excluidos=1` lista só eles, com a data, e `POST /contacts/{id}/restore` os devolve —
 * com a mesma permissão de excluir e só da empresa da sessão.
 *
 * Carga parcial com os headers do @inertiajs/core (§5 2026-09-08). Tenant 98 × 99 (ADR 0358).
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('contacts')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->marca = 'ZQE'.random_int(1000, 9999);
    $this->novoContato = function (int $businessId, int $createdBy, string $nome, bool $excluido): int {
        $linha = [
            'business_id' => $businessId, 'created_by' => $createdBy, 'type' => 'customer',
            'name' => $nome, 'contact_status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
            'deleted_at' => $excluido ? now()->subDay() : null,
        ];
        if (Schema::hasColumn('contacts', 'is_customer')) {
            $linha['is_customer'] = 1;
        }

        return (int) DB::table('contacts')->insertGetId($linha);
    };
});

function ceEntrar($teste, array $permissoes): \App\User
{
    $user = $teste->usuarioComPermissoes($permissoes, $teste->business);
    $teste->actingAs($user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $user->id, 'business.id' => $teste->business->id]);

    return $user;
}

/** Linhas da lista /cliente (prop `customers`), só as desta fixture, por nome. */
function ceLista($teste, bool $excluidos): array
{
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $query = ['type' => 'customer', 'q' => $teste->marca] + ($excluidos ? ['excluidos' => 1] : []);
    $r = $teste->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $versao,
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Partial-Component' => 'Cliente/Index',
        'X-Inertia-Partial-Data' => 'customers',
        'Accept' => 'text/html, application/xhtml+xml',
    ])->get('/cliente?'.http_build_query($query));
    $r->assertOk();

    return collect($r->json('props.customers.data'))->keyBy('name')->all();
}

function ceRestaurar($teste, int $id)
{
    return $teste->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
        ->post("/contacts/{$id}/restore");
}

test('UC-CIDX-16 o filtro Excluídos traz só os excluídos, com a data; sem ele, o excluído não aparece', function () {
    $user = ceEntrar($this, ['customer.view', 'customer.delete']);
    ($this->novoContato)($this->business->id, $user->id, "{$this->marca} Ativo", false);
    ($this->novoContato)($this->business->id, $user->id, "{$this->marca} Excluido", true);

    $normal = ceLista($this, false);
    expect(array_keys($normal))->toBe(["{$this->marca} Ativo"]);

    $excluidos = ceLista($this, true);
    expect(array_keys($excluidos))->toBe(["{$this->marca} Excluido"]);
    expect($excluidos["{$this->marca} Excluido"]['excluido_em'])->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/');
    // Na lista normal o campo vem nulo.
    expect($normal["{$this->marca} Ativo"]['excluido_em'])->toBeNull();
});

test('UC-CIDX-16 Restaurar devolve o cadastro à lista normal', function () {
    $user = ceEntrar($this, ['customer.view', 'customer.delete']);
    $id = ($this->novoContato)($this->business->id, $user->id, "{$this->marca} Volta", true);

    ceRestaurar($this, $id)->assertOk()->assertJsonPath('success', true);

    expect(DB::table('contacts')->where('id', $id)->value('deleted_at'))->toBeNull();
    expect(array_keys(ceLista($this, false)))->toContain("{$this->marca} Volta");
});

test('UC-CIDX-16 Restaurar exige "Remover cliente": sem ela, 403 e o cadastro segue excluído', function () {
    $user = ceEntrar($this, ['customer.view']);
    $id = ($this->novoContato)($this->business->id, $user->id, "{$this->marca} Fica", true);

    ceRestaurar($this, $id)->assertForbidden();

    expect(DB::table('contacts')->where('id', $id)->value('deleted_at'))->not->toBeNull();
});

test('UC-CIDX-16 Tier 0 — excluído de outra empresa não aparece no filtro nem é restaurado (404)', function () {
    $user = ceEntrar($this, ['customer.view', 'customer.delete']);
    $outra = $this->seededSupportClientTenant();
    $alheio = ($this->novoContato)((int) $outra->id, $user->id, "{$this->marca} Alheio", true);
    ($this->novoContato)($this->business->id, $user->id, "{$this->marca} Meu", true);

    // Controle: o filtro devolve o excluído da própria empresa.
    $lista = ceLista($this, true);
    expect(array_keys($lista))->toBe(["{$this->marca} Meu"]);

    ceRestaurar($this, $alheio)->assertNotFound();
    expect(DB::table('contacts')->where('id', $alheio)->value('deleted_at'))->not->toBeNull();
});
