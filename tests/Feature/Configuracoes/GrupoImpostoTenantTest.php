<?php

declare(strict_types=1);
// Cobre UC-GIMP-01..07 — GroupTaxController (grupo de impostos): sub-imposto só da própria
// empresa e permissão em todos os métodos. Achado do _saida-05 do Sistema (thread 05).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Antes: `store()`/`update()` buscavam os sub-impostos por id sem filtrar a empresa. Um POST com
 * id de alíquota de outro negócio somava a alíquota dele no total do grupo e criava o vínculo
 * (Tier 0, ADR 0093 — `TaxRate` não tem global scope). E nenhum método conferia permissão.
 *
 * Valor (REGRA MESTRE): num pedido legítimo o total do grupo não muda — UC-GIMP-01 prova por dois
 * caminhos (conta à mão e o número gravado). O que muda é só o pedido forjado, que passa a ser
 * recusado sem gravar nada.
 *
 * Tenant de teste 98 × cliente fictício 99 (ADR 0358). DatabaseTransactions.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('tax_rates') || ! Schema::hasTable('group_sub_taxes')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->outro = $this->seededSupportClientTenant();
    $this->user = $this->usuarioComPermissoes(['tax_rate.view', 'tax_rate.create', 'tax_rate.update', 'tax_rate.delete'], $this->business);
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
    $this->ajax = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];
});

function gimpAliquota(int $businessId, int $criadoPor, string $nome, float $valor, int $grupo = 0): int
{
    return DB::table('tax_rates')->insertGetId([
        'business_id' => $businessId, 'name' => $nome, 'amount' => $valor, 'is_tax_group' => $grupo,
        'for_tax_group' => 0, 'created_by' => $criadoPor, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function gimpGrupoPorNome(int $businessId, string $nome): ?object
{
    return DB::table('tax_rates')->where('business_id', $businessId)->where('name', $nome)->where('is_tax_group', 1)->first();
}

test('UC-GIMP-01 valor — grupo com alíquotas da própria empresa grava a soma (dupla prova)', function () {
    $pis = gimpAliquota($this->business->id, $this->user->id, 'PIS '.uniqid(), 1.65);
    $cofins = gimpAliquota($this->business->id, $this->user->id, 'COFINS '.uniqid(), 7.6);
    $nome = 'PIS+COFINS '.uniqid();

    $r = $this->withHeaders($this->ajax)->post('/group-taxes', ['name' => $nome, 'taxes' => [$pis, $cofins]]);

    expect($r->json('success'))->toBeTrue();
    $grupo = gimpGrupoPorNome($this->business->id, $nome);
    expect($grupo)->not->toBeNull();
    // Caminho 1: conta à mão. Caminho 2: o número gravado pelo endpoint.
    expect(round(1.65 + 7.6, 4))->toBe(9.25);
    expect((float) $grupo->amount)->toBe(9.25);
    $vinculos = DB::table('group_sub_taxes')->where('group_tax_id', $grupo->id)->pluck('tax_id')->map(fn ($v) => (int) $v)->sort()->values()->all();
    expect($vinculos)->toBe(collect([$pis, $cofins])->sort()->values()->all());
});

test('UC-GIMP-02 Tier 0 — alíquota de OUTRA empresa como sub-imposto é recusada e nada é gravado', function () {
    $alheia = gimpAliquota($this->outro->id, $this->user->id, 'ICMS alheio '.uniqid(), 18);
    $nome = 'Forjado '.uniqid();
    $vinculosAntes = DB::table('group_sub_taxes')->where('tax_id', $alheia)->count();

    $r = $this->withHeaders($this->ajax)->post('/group-taxes', ['name' => $nome, 'taxes' => [$alheia]]);

    expect($r->json('success'))->toBeFalse();
    expect(gimpGrupoPorNome($this->business->id, $nome))->toBeNull();
    expect(DB::table('group_sub_taxes')->where('tax_id', $alheia)->count())->toBe($vinculosAntes);
});

test('UC-GIMP-03 Tier 0 — mistura de própria e alheia também é recusada (não grava só a parte válida)', function () {
    $pis = gimpAliquota($this->business->id, $this->user->id, 'PIS '.uniqid(), 1.65);
    $alheia = gimpAliquota($this->outro->id, $this->user->id, 'ICMS alheio '.uniqid(), 18);
    $nome = 'Misto '.uniqid();

    $r = $this->withHeaders($this->ajax)->post('/group-taxes', ['name' => $nome, 'taxes' => [$pis, $alheia]]);

    expect($r->json('success'))->toBeFalse();
    expect(gimpGrupoPorNome($this->business->id, $nome))->toBeNull();
});

test('UC-GIMP-04 — um GRUPO não entra como sub-imposto de outro grupo', function () {
    $pis = gimpAliquota($this->business->id, $this->user->id, 'PIS '.uniqid(), 1.65);
    $grupoExistente = gimpAliquota($this->business->id, $this->user->id, 'Grupo '.uniqid(), 9.25, 1);
    $nome = 'Aninhado '.uniqid();

    $r = $this->withHeaders($this->ajax)->post('/group-taxes', ['name' => $nome, 'taxes' => [$pis, $grupoExistente]]);

    expect($r->json('success'))->toBeFalse();
    expect(gimpGrupoPorNome($this->business->id, $nome))->toBeNull();
});

test('UC-GIMP-05 Tier 0 — editar o grupo pondo alíquota alheia é recusado e o grupo fica como estava', function () {
    $pis = gimpAliquota($this->business->id, $this->user->id, 'PIS '.uniqid(), 1.65);
    $grupo = gimpAliquota($this->business->id, $this->user->id, 'Grupo '.uniqid(), 1.65, 1);
    DB::table('group_sub_taxes')->insert(['group_tax_id' => $grupo, 'tax_id' => $pis]);
    $alheia = gimpAliquota($this->outro->id, $this->user->id, 'ICMS alheio '.uniqid(), 18);

    $r = $this->withHeaders($this->ajax)->put("/group-taxes/{$grupo}", ['name' => 'Renomeado', 'taxes' => [$pis, $alheia]]);

    expect($r->json('success'))->toBeFalse();
    $depois = DB::table('tax_rates')->where('id', $grupo)->first();
    expect((float) $depois->amount)->toBe(1.65);
    expect($depois->name)->not->toBe('Renomeado');
    expect(DB::table('group_sub_taxes')->where('group_tax_id', $grupo)->pluck('tax_id')->map(fn ($v) => (int) $v)->all())->toBe([$pis]);
});

test('UC-GIMP-06 permissão — sem tax_rate.create o store responde 403 e não grava', function () {
    $this->actingAs($this->usuarioComPermissoes(['tax_rate.view'], $this->business));
    $pis = gimpAliquota($this->business->id, $this->user->id, 'PIS '.uniqid(), 1.65);
    $nome = 'Sem permissão '.uniqid();

    $this->withHeaders($this->ajax)->post('/group-taxes', ['name' => $nome, 'taxes' => [$pis]])->assertForbidden();

    expect(gimpGrupoPorNome($this->business->id, $nome))->toBeNull();
});

test('UC-GIMP-07 permissão — sem tax_rate.delete o destroy responde 403 e o grupo continua', function () {
    $this->actingAs($this->usuarioComPermissoes(['tax_rate.view', 'tax_rate.update'], $this->business));
    $grupo = gimpAliquota($this->business->id, $this->user->id, 'Grupo '.uniqid(), 9.25, 1);

    $this->withHeaders($this->ajax)->delete("/group-taxes/{$grupo}")->assertForbidden();

    expect(DB::table('tax_rates')->where('id', $grupo)->whereNull('deleted_at')->exists())->toBeTrue();
});
