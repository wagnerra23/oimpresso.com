<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Thread restaurante/playbook/02, F2 — o que a Blade de Modificadores (/modules/modifiers) lista e mostra HOJE, antes da
 * tela React. Trava a DataTable do ModifierSetsController::index: só conjuntos do negócio, 403 sem product.view nem
 * product.create, editar só com product.update e excluir só com product.delete. Tenant 98 × 99 (ADR 0358).
 *
 * @see memory/requisitos/Restaurante/RUNBOOK-modificadores.md
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('res_product_modifier_sets') || ! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
});

function modBaseEntrar($teste, \App\User $user): void
{
    $teste->actingAs($user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $user->id, 'business.id' => $teste->business->id]);
}

/** Conjunto de modificadores = produto `type = modifier` com uma variação por modificador. */
function modBaseConjunto(int $businessId, int $criador, string $nome, array $modificadores): int
{
    $id = DB::table('products')->insertGetId([
        'name' => $nome, 'business_id' => $businessId, 'type' => 'modifier', 'tax_type' => 'inclusive',
        'sku' => 'MOD-'.uniqid(), 'created_by' => $criador, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $pv = DB::table('product_variations')->insertGetId(['name' => 'DUMMY', 'product_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
    foreach ($modificadores as $m) {
        DB::table('variations')->insert([
            'name' => $m, 'product_id' => $id, 'product_variation_id' => $pv, 'sub_sku' => 'MODV-'.uniqid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return $id;
}

function modBaseDataTable($teste): array
{
    $r = $teste->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get('/modules/modifiers?'.http_build_query(['draw' => 1, 'start' => 0, 'length' => -1]));
    $r->assertOk();

    return $r->json('data');
}

test('a DataTable da Blade lista só os conjuntos do negócio da sessão, com os nomes dos modificadores', function () {
    $user = $this->usuarioComPermissoes(['product.view'], $this->business);
    modBaseEntrar($this, $user);
    $outro = $this->seededSupportClientTenant();
    $meu = 'Acabamento '.uniqid();
    $alheio = 'Alheio '.uniqid();
    modBaseConjunto($this->business->id, $user->id, $meu, ['Bastão', 'Ilhós']);
    modBaseConjunto($outro->id, $user->id, $alheio, ['X']);

    $linhas = collect(modBaseDataTable($this))->keyBy('name');

    expect($linhas->has($meu))->toBeTrue();
    expect($linhas->has($alheio))->toBeFalse();
    expect($linhas[$meu]['variations'])->toBe('Bastão, Ilhós');
});

test('sem product.view nem product.create a rota devolve 403', function () {
    modBaseEntrar($this, $this->usuarioComPermissoes([], $this->business));

    $this->get('/modules/modifiers')->assertForbidden();
});

test('editar exige product.update e excluir exige product.delete na DataTable', function () {
    $leitor = $this->usuarioComPermissoes(['product.view'], $this->business);
    $nome = 'Botoes '.uniqid();
    modBaseConjunto($this->business->id, $leitor->id, $nome, ['A']);

    modBaseEntrar($this, $leitor);
    $acao = (string) collect(modBaseDataTable($this))->firstWhere('name', $nome)['action'];
    expect($acao)->not->toContain('edit_modifier_button');
    expect($acao)->not->toContain('delete_modifier_button');

    modBaseEntrar($this, $this->usuarioComPermissoes(['product.view', 'product.update', 'product.delete'], $this->business));
    $acao = (string) collect(modBaseDataTable($this))->firstWhere('name', $nome)['action'];
    expect($acao)->toContain('edit_modifier_button');
    expect($acao)->toContain('delete_modifier_button');
});
