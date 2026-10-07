<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Baseline F2 (MWART, ADR 0104) de /types-of-service — o que a Blade grava ANTES da tela React.
 *
 * Thread sistema/playbook/05, tela 2 de 3. `packing_charge` entra no total da venda (regra mestre de valor): o caso
 * de valor prova o mesmo número por dois caminhos — o endpoint gravando e o `num_uf` aplicado ao mesmo texto.
 * Mapa: memory/requisitos/Configuracoes/tipos-servico-parity.md.
 *
 * Prefixo `tsBase` nas funções (o escopo global do Pest é compartilhado). Tenant 98 x 99 (ADR 0358).
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('types_of_services') || ! Schema::hasTable('selling_price_groups')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['access_types_of_service'], $this->business);
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
    $this->ajax = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];
});

function tsBaseTipo(int $businessId, array $campos = []): int
{
    return DB::table('types_of_services')->insertGetId(array_merge([
        'business_id' => $businessId, 'name' => 'Tipo '.uniqid(), 'packing_charge' => 0, 'packing_charge_type' => 'fixed',
        'enable_custom_fields' => 0, 'created_at' => now(), 'updated_at' => now(),
    ], $campos));
}

test('baseline: a lista (DataTable) traz só os tipos do negócio', function () {
    $outro = $this->seededSupportClientTenant();
    $meu = 'Balcão '.uniqid();
    $alheio = 'Entrega alheia '.uniqid();
    tsBaseTipo($this->business->id, ['name' => $meu]);
    tsBaseTipo($outro->id, ['name' => $alheio]);

    $r = $this->withHeaders($this->ajax)->get('/types-of-service');
    $r->assertOk();
    $nomes = collect($r->json('data'))->pluck('name')->implode('|');

    expect($nomes)->toContain($meu);
    expect($nomes)->not->toContain($alheio);
});

test('baseline valor: a taxa gravada é a mesma que o num_uf devolve para o texto, no cadastro e na edição', function () {
    $util = new \App\Utils\Util;
    // Texto que a Blade (input_number) e a tela nova mandam: vírgula como decimal.
    $casos = ['35,00' => 35.0, '8,00' => 8.0, '1.234,56' => 1234.56, '0,5' => 0.5];

    foreach ($casos as $texto => $esperado) {
        $nome = 'Tipo '.uniqid();
        $r = $this->withHeaders($this->ajax)->post('/types-of-service', ['name' => $nome, 'packing_charge_type' => 'fixed', 'packing_charge' => $texto]);
        expect($r->json('success'))->toBeTrue();

        $gravado = (float) DB::table('types_of_services')->where('business_id', $this->business->id)->where('name', $nome)->value('packing_charge');
        // Caminho 1: o endpoint gravou. Caminho 2: num_uf direto sobre o mesmo texto.
        expect($gravado)->toBe($esperado);
        expect((float) $util->num_uf($texto))->toBe($esperado);
    }

    $vazio = 'Sem taxa '.uniqid();
    $this->withHeaders($this->ajax)->post('/types-of-service', ['name' => $vazio, 'packing_charge_type' => 'fixed', 'packing_charge' => ''])->assertOk();
    expect((float) DB::table('types_of_services')->where('name', $vazio)->value('packing_charge'))->toBe(0.0);

    $id = tsBaseTipo($this->business->id);
    $this->withHeaders($this->ajax)->put("/types-of-service/{$id}", ['name' => 'Editado', 'packing_charge_type' => 'percent', 'packing_charge' => '8,50'])->assertOk();
    $editado = DB::table('types_of_services')->where('id', $id)->first();
    expect((float) $editado->packing_charge)->toBe(8.5);
    expect($editado->packing_charge_type)->toBe('percent');
});

test('baseline preço: a tabela de preço por local chega igual pelo cadastro e pela edição', function () {
    $local = DB::table('business_locations')->where('business_id', $this->business->id)->value('id');
    expect($local)->not->toBeNull();
    $tabela = DB::table('selling_price_groups')->insertGetId(['business_id' => $this->business->id, 'name' => 'Atacado '.uniqid(), 'is_active' => 1]);
    $nome = 'Com tabela '.uniqid();

    $this->withHeaders($this->ajax)->post('/types-of-service', [
        'name' => $nome, 'packing_charge_type' => 'fixed', 'packing_charge' => '0',
        'location_price_group' => [$local => (string) $tabela], 'enable_custom_fields' => '1',
    ])->assertOk();
    $criado = DB::table('types_of_services')->where('name', $nome)->first();
    expect(json_decode($criado->location_price_group, true))->toBe([(string) $local => (string) $tabela]);
    expect((int) $criado->enable_custom_fields)->toBe(1);

    // update() faz json_encode à mão (query builder, sem o cast do model). Troca para OUTRA tabela: editar para o
    // mesmo valor não distinguiria "gravou" de "falhou e ficou como estava".
    $varejo = DB::table('selling_price_groups')->insertGetId(['business_id' => $this->business->id, 'name' => 'Varejo '.uniqid(), 'is_active' => 1]);
    $r = $this->withHeaders($this->ajax)->put("/types-of-service/{$criado->id}", [
        'name' => $nome, 'packing_charge_type' => 'fixed', 'packing_charge' => '0', 'location_price_group' => [$local => (string) $varejo],
    ]);
    expect($r->json('success'))->toBeTrue();
    $editado = DB::table('types_of_services')->where('id', $criado->id)->first();
    expect(json_decode($editado->location_price_group, true))->toBe([(string) $local => (string) $varejo]);
    expect((int) $editado->enable_custom_fields)->toBe(0);
});

test('baseline: editar e excluir não alcançam o tipo de outro negócio', function () {
    $outro = $this->seededSupportClientTenant();
    $alheio = tsBaseTipo($outro->id, ['name' => 'Alheio intocado', 'packing_charge' => 35]);
    $meu = tsBaseTipo($this->business->id);

    $this->withHeaders($this->ajax)->put("/types-of-service/{$alheio}", ['name' => 'Invadido', 'packing_charge_type' => 'fixed', 'packing_charge' => '0']);
    $this->withHeaders($this->ajax)->delete("/types-of-service/{$alheio}");
    $depois = DB::table('types_of_services')->where('id', $alheio)->first();
    expect($depois)->not->toBeNull();
    expect($depois->name)->toBe('Alheio intocado');
    expect((float) $depois->packing_charge)->toBe(35.0);

    // Contraprova: o do próprio negócio é excluído.
    $this->withHeaders($this->ajax)->delete("/types-of-service/{$meu}")->assertOk();
    expect(DB::table('types_of_services')->where('id', $meu)->exists())->toBeFalse();
});

test('baseline: sem access_types_of_service a lista e o cadastro devolvem 403', function () {
    $this->actingAs($this->usuarioComPermissoes([], $this->business));
    $antes = DB::table('types_of_services')->where('business_id', $this->business->id)->count();

    $this->get('/types-of-service')->assertForbidden();
    $this->withHeaders($this->ajax)->post('/types-of-service', ['name' => 'Não grava'])->assertForbidden();
    expect(DB::table('types_of_services')->where('business_id', $this->business->id)->count())->toBe($antes);
});
