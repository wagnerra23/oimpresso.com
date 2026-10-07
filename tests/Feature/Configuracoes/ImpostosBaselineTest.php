<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Baseline F2 (MWART, ADR 0104) de /tax-rates — o que a Blade grava ANTES da tela React.
 *
 * Thread sistema/playbook/05, tela 1 de 3. `amount` é a alíquota que entra no imposto da venda (regra mestre de
 * valor): o caso de valor prova o mesmo número por dois caminhos — o endpoint gravando e o `num_uf` aplicado ao mesmo
 * texto. Mapa: memory/requisitos/Configuracoes/impostos-parity.md.
 *
 * Prefixo `impBase` nas funções (o escopo global do Pest é compartilhado). Tenant 98 x 99 (ADR 0358).
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('tax_rates') || ! Schema::hasTable('group_sub_taxes')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['tax_rate.view', 'tax_rate.create', 'tax_rate.update', 'tax_rate.delete'], $this->business);
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
    $this->ajax = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];
});

function impBaseAliquota(int $businessId, int $criadoPor, float $valor, array $campos = []): int
{
    return DB::table('tax_rates')->insertGetId(array_merge([
        'business_id' => $businessId, 'name' => 'Aliq '.uniqid(), 'amount' => $valor, 'is_tax_group' => 0,
        'for_tax_group' => 0, 'created_by' => $criadoPor, 'created_at' => now(), 'updated_at' => now(),
    ], $campos));
}

test('baseline: a lista (DataTable) traz só as alíquotas do negócio', function () {
    $outro = $this->seededSupportClientTenant();
    $minha = 'ICMS Meu '.uniqid();
    $alheia = 'ICMS Alheio '.uniqid();
    impBaseAliquota($this->business->id, $this->user->id, 18, ['name' => $minha]);
    impBaseAliquota($outro->id, $this->user->id, 18, ['name' => $alheia]);

    // A coluna da alíquota usa @num_format, que lê session('currency') — o SetSessionData do login real preenche.
    // Sem ela o DataTables engole o erro e devolve a lista vazia.
    session(['currency' => ['id' => 1, 'code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']]);
    $r = $this->withHeaders($this->ajax)->get('/tax-rates');
    $r->assertOk();
    $nomes = collect($r->json('data'))->pluck(0)->implode('|');

    expect($nomes)->toContain($minha);
    expect($nomes)->not->toContain($alheia);
});

test('baseline valor: a alíquota gravada é a mesma que o num_uf devolve para o texto, no cadastro e na edição', function () {
    $util = new \App\Utils\Util;
    // Texto que a Blade (input_number) e a tela nova mandam: sempre vírgula como decimal.
    $casos = ['18,00' => 18.0, '1,65' => 1.65, '7,6' => 7.6, '0' => 0.0, '12' => 12.0];

    foreach ($casos as $texto => $esperado) {
        $nome = 'Aliq '.uniqid();
        $r = $this->withHeaders($this->ajax)->post('/tax-rates', ['name' => $nome, 'amount' => $texto]);
        expect($r->json('success'))->toBeTrue();

        $gravado = (float) DB::table('tax_rates')->where('business_id', $this->business->id)->where('name', $nome)->value('amount');
        // Caminho 1: o endpoint gravou. Caminho 2: num_uf direto sobre o mesmo texto.
        expect($gravado)->toBe($esperado);
        expect((float) $util->num_uf($texto))->toBe($esperado);
    }

    $id = impBaseAliquota($this->business->id, $this->user->id, 5);
    $this->withHeaders($this->ajax)->put("/tax-rates/{$id}", ['name' => 'Editada', 'amount' => '9,25'])->assertOk();
    expect((float) DB::table('tax_rates')->where('id', $id)->value('amount'))->toBe(9.25);
});

test('baseline: cadastrar grava no negócio, com created_by e "só em grupo"', function () {
    $nome = 'PIS '.uniqid();
    $this->withHeaders($this->ajax)->post('/tax-rates', ['name' => $nome, 'amount' => '1,65', 'for_tax_group' => '1'])->assertOk();

    $linha = DB::table('tax_rates')->where('name', $nome)->first();
    expect($linha)->not->toBeNull();
    expect((int) $linha->business_id)->toBe((int) $this->business->id);
    expect((int) $linha->created_by)->toBe((int) $this->user->id);
    expect((int) $linha->for_tax_group)->toBe(1);
});

test('baseline valor: editar uma alíquota recalcula a alíquota do grupo que a usa', function () {
    $pis = impBaseAliquota($this->business->id, $this->user->id, 1.65);
    $cofins = impBaseAliquota($this->business->id, $this->user->id, 7.6);
    $grupo = impBaseAliquota($this->business->id, $this->user->id, 9.25, ['is_tax_group' => 1, 'name' => 'PIS+COFINS '.uniqid()]);
    DB::table('group_sub_taxes')->insert([['group_tax_id' => $grupo, 'tax_id' => $pis], ['group_tax_id' => $grupo, 'tax_id' => $cofins]]);

    $this->withHeaders($this->ajax)->put("/tax-rates/{$pis}", ['name' => 'PIS', 'amount' => '2,00'])->assertOk();

    // 2,00 + 7,60 = 9,60 — recomputado à mão e pelo banco.
    expect((float) DB::table('tax_rates')->where('id', $grupo)->value('amount'))->toBe(9.6);
});

test('baseline: excluir é recusado quando a alíquota está num grupo, e funciona fora dele', function () {
    $emGrupo = impBaseAliquota($this->business->id, $this->user->id, 1.65);
    $grupo = impBaseAliquota($this->business->id, $this->user->id, 1.65, ['is_tax_group' => 1]);
    DB::table('group_sub_taxes')->insert(['group_tax_id' => $grupo, 'tax_id' => $emGrupo]);
    $solta = impBaseAliquota($this->business->id, $this->user->id, 5);

    $r = $this->withHeaders($this->ajax)->delete("/tax-rates/{$emGrupo}");
    expect($r->json('success'))->toBeFalse();
    expect(DB::table('tax_rates')->where('id', $emGrupo)->whereNull('deleted_at')->exists())->toBeTrue();

    $r = $this->withHeaders($this->ajax)->delete("/tax-rates/{$solta}");
    expect($r->json('success'))->toBeTrue();
    expect(DB::table('tax_rates')->where('id', $solta)->whereNull('deleted_at')->exists())->toBeFalse();
});

test('baseline: editar e excluir não alcançam a alíquota de outro negócio', function () {
    $outro = $this->seededSupportClientTenant();
    $alheia = impBaseAliquota($outro->id, $this->user->id, 18, ['name' => 'Alheia intocada']);
    $minha = impBaseAliquota($this->business->id, $this->user->id, 18);

    $this->withHeaders($this->ajax)->put("/tax-rates/{$alheia}", ['name' => 'Invadida', 'amount' => '0']);
    $r = $this->withHeaders($this->ajax)->delete("/tax-rates/{$alheia}");
    expect($r->json('success'))->toBeFalse();
    $depois = DB::table('tax_rates')->where('id', $alheia)->first();
    expect($depois->name)->toBe('Alheia intocada');
    expect((float) $depois->amount)->toBe(18.0);
    expect($depois->deleted_at)->toBeNull();

    // Contraprova: a do próprio negócio é editada.
    $this->withHeaders($this->ajax)->put("/tax-rates/{$minha}", ['name' => 'Minha editada', 'amount' => '17'])->assertOk();
    expect(DB::table('tax_rates')->where('id', $minha)->value('name'))->toBe('Minha editada');
});

test('baseline: sem tax_rate.view nem .create a lista devolve 403; sem .create o cadastro também', function () {
    $this->actingAs($this->usuarioComPermissoes([], $this->business));
    $antes = DB::table('tax_rates')->where('business_id', $this->business->id)->count();

    $this->get('/tax-rates')->assertForbidden();
    $this->withHeaders($this->ajax)->post('/tax-rates', ['name' => 'Não grava', 'amount' => '1'])->assertForbidden();
    expect(DB::table('tax_rates')->where('business_id', $this->business->id)->count())->toBe($antes);
});
