<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Baseline F2 (MWART, ADR 0104) de /barcodes — o que a Blade grava ANTES da tela React.
 *
 * Thread sistema/playbook/04, tela 2 de 3. O isolamento de editar/excluir/tornar padrão mora no
 * CodigoBarrasTenantTest (#8924); aqui ficam lista, cadastro (folha × rolo contínuo), padrão ao
 * cadastrar e a permissão. Mapa: memory/requisitos/Configuracoes/codigo-barras-parity.md.
 *
 * Prefixo `etqBase` nas funções: o CodigoBarrasTenantTest, na mesma pasta, já declara `etqTenant`.
 * Tenant de teste 98 x cliente fictício 99 (ADR 0358).
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('barcodes') || ! Schema::hasColumn('users', 'business_id')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['barcode_settings.access'], $this->business);
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
});

function etqBaseCorpo(string $nome, array $extra = []): array
{
    return array_merge(['name' => $nome, 'description' => 'x', 'width' => '1.5', 'height' => '1', 'paper_width' => '8.5',
        'paper_height' => '11', 'top_margin' => '0.1', 'left_margin' => '0.1', 'row_distance' => '0',
        'col_distance' => '0', 'stickers_in_one_row' => '3', 'stickers_in_one_sheet' => '24'], $extra);
}

test('baseline: a lista (DataTable) traz só as configurações do negócio, com a padrão marcada', function () {
    $outro = $this->seededSupportClientTenant();
    $nomeMeu = 'Folha Minha '.uniqid();
    $nomeAlheio = 'Folha Alheia '.uniqid();
    $nomeGlobal = 'Modelo Global '.uniqid();
    DB::table('barcodes')->insert([
        ['business_id' => $this->business->id, 'name' => $nomeMeu, 'is_default' => 1, 'is_continuous' => 0],
        ['business_id' => $outro->id, 'name' => $nomeAlheio, 'is_default' => 0, 'is_continuous' => 0],
        ['business_id' => null, 'name' => $nomeGlobal, 'is_default' => 0, 'is_continuous' => 0],
    ]);

    $r = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])->get('/barcodes');
    $r->assertOk();
    $nomes = collect($r->json('data'))->pluck(0)->implode('|');

    expect($nomes)->toContain($nomeMeu);
    expect($nomes)->toContain(__('barcode.default'));
    expect($nomes)->not->toContain($nomeAlheio);
    expect($nomes)->not->toContain($nomeGlobal);
});

test('baseline: cadastrar em folha grava no negócio com etiquetas por folha e altura do papel do formulário', function () {
    $nome = 'Folha '.uniqid();
    $this->post('/barcodes', etqBaseCorpo($nome))->assertRedirect();

    $linha = DB::table('barcodes')->where('name', $nome)->first();
    expect($linha)->not->toBeNull();
    expect((int) $linha->business_id)->toBe((int) $this->business->id);
    expect((int) $linha->is_continuous)->toBe(0);
    expect((int) $linha->stickers_in_one_sheet)->toBe(24);
    expect((float) $linha->paper_height)->toBe(11.0);
});

test('baseline: rolo contínuo grava is_continuous e força 28 etiquetas por folha', function () {
    $nome = 'Rolo '.uniqid();
    $this->post('/barcodes', etqBaseCorpo($nome, ['is_continuous' => '1', 'stickers_in_one_sheet' => '5']))->assertRedirect();

    $linha = DB::table('barcodes')->where('name', $nome)->first();
    expect($linha)->not->toBeNull();
    expect((int) $linha->is_continuous)->toBe(1);
    expect((int) $linha->stickers_in_one_sheet)->toBe(28);
});

test('baseline: cadastrar como padrão desmarca a padrão anterior do negócio', function () {
    $antiga = DB::table('barcodes')->insertGetId(['business_id' => $this->business->id, 'name' => 'Antiga', 'is_default' => 1, 'is_continuous' => 0]);
    $nome = 'Nova Padrão '.uniqid();

    $this->post('/barcodes', etqBaseCorpo($nome, ['is_default' => '1']))->assertRedirect();

    expect((int) DB::table('barcodes')->where('name', $nome)->value('is_default'))->toBe(1);
    expect((int) DB::table('barcodes')->where('id', $antiga)->value('is_default'))->toBe(0);
});

test('baseline: sem barcode_settings.access a lista e o cadastro devolvem 403', function () {
    $this->actingAs($this->usuarioComPermissoes([], $this->business));
    $antes = DB::table('barcodes')->where('business_id', $this->business->id)->count();

    $this->get('/barcodes')->assertForbidden();
    $this->post('/barcodes', etqBaseCorpo('Não grava'))->assertForbidden();
    expect(DB::table('barcodes')->where('business_id', $this->business->id)->count())->toBe($antes);
});
