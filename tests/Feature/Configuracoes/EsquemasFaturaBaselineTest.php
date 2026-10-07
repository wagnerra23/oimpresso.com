<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Baseline F2 (MWART, ADR 0104) de /invoice-schemes — o que a Blade grava ANTES da tela React.
 *
 * Thread sistema/playbook/05, tela 3 de 3. O isolamento de editar/excluir/tornar padrão mora no
 * EsquemaFaturaTenantTest (#8979); aqui ficam lista, cadastro, padrão ao cadastrar, a aba de layouts e a permissão.
 * Mapa: memory/requisitos/Configuracoes/esquemas-fatura-parity.md.
 *
 * Prefixo `esqBase` nas funções (o EsquemaFaturaTenantTest declara `esqTenant`). Tenant 98 x 99 (ADR 0358).
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('invoice_schemes') || ! Schema::hasTable('invoice_layouts')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['invoice_settings.access'], $this->business);
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
    $this->ajax = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];
});

function esqBaseCorpo(string $nome, array $extra = []): array
{
    return array_merge(['name' => $nome, 'scheme_type' => 'blank', 'prefix' => 'OS', 'start_number' => '100',
        'total_digits' => '5', 'number_type' => 'sequential'], $extra);
}

test('baseline: a lista (DataTable) traz só os esquemas do negócio, com o padrão marcado', function () {
    $outro = $this->seededSupportClientTenant();
    $meu = 'Padrão Meu '.uniqid();
    $alheio = 'Alheio '.uniqid();
    DB::table('invoice_schemes')->insert([
        ['business_id' => $this->business->id, 'name' => $meu, 'scheme_type' => 'blank', 'number_type' => 'sequential', 'is_default' => 1],
        ['business_id' => $outro->id, 'name' => $alheio, 'scheme_type' => 'blank', 'number_type' => 'sequential', 'is_default' => 0],
    ]);

    $r = $this->withHeaders($this->ajax)->get('/invoice-schemes');
    $r->assertOk();
    $nomes = collect($r->json('data'))->pluck(0)->implode('|');

    expect($nomes)->toContain($meu);
    expect($nomes)->toContain(__('barcode.default'));
    expect($nomes)->not->toContain($alheio);
});

test('baseline: cadastrar grava no negócio com a numeração do formulário', function () {
    $nome = 'Oficina '.uniqid();
    $r = $this->withHeaders($this->ajax)->post('/invoice-schemes', esqBaseCorpo($nome, ['scheme_type' => 'year']));
    expect($r->json('success'))->toBeTrue();

    $linha = DB::table('invoice_schemes')->where('name', $nome)->first();
    expect($linha)->not->toBeNull();
    expect((int) $linha->business_id)->toBe((int) $this->business->id);
    expect($linha->scheme_type)->toBe('year');
    expect($linha->prefix)->toBe('OS');
    expect((int) $linha->start_number)->toBe(100);
    expect((int) $linha->total_digits)->toBe(5);
    expect((int) $linha->is_default)->toBe(0);
});

test('baseline (quirk registrado): numeração aleatória mantém o número inicial — o controller compara com "aleatory"', function () {
    $nome = 'Aleatório '.uniqid();
    $this->withHeaders($this->ajax)->post('/invoice-schemes', esqBaseCorpo($nome, ['number_type' => 'random', 'start_number' => '7']))->assertOk();

    $linha = DB::table('invoice_schemes')->where('name', $nome)->first();
    expect($linha->number_type)->toBe('random');
    expect((int) $linha->start_number)->toBe(7);
});

test('baseline: cadastrar como padrão desmarca o padrão anterior do negócio', function () {
    $antigo = DB::table('invoice_schemes')->insertGetId(['business_id' => $this->business->id, 'name' => 'Antigo', 'scheme_type' => 'blank', 'number_type' => 'sequential', 'is_default' => 1]);
    $nome = 'Novo padrão '.uniqid();

    $this->withHeaders($this->ajax)->post('/invoice-schemes', esqBaseCorpo($nome, ['is_default' => '1']))->assertOk();

    expect((int) DB::table('invoice_schemes')->where('name', $nome)->value('is_default'))->toBe(1);
    expect((int) DB::table('invoice_schemes')->where('id', $antigo)->value('is_default'))->toBe(0);
});

test('baseline: a aba de layouts traz só os layouts do negócio', function () {
    $outro = $this->seededSupportClientTenant();
    $meu = DB::table('invoice_layouts')->insertGetId(['business_id' => $this->business->id, 'name' => 'Cupom Meu '.uniqid()]);
    $alheio = DB::table('invoice_layouts')->insertGetId(['business_id' => $outro->id, 'name' => 'Cupom Alheio '.uniqid()]);

    $r = $this->get('/invoice-schemes');
    $r->assertOk()->assertViewIs('invoice_scheme.index');
    $ids = collect($r->viewData('invoice_layouts'))->pluck('id');

    expect($ids)->toContain($meu);
    expect($ids)->not->toContain($alheio);
});

test('baseline: sem invoice_settings.access a lista e o cadastro devolvem 403', function () {
    $this->actingAs($this->usuarioComPermissoes([], $this->business));
    $antes = DB::table('invoice_schemes')->where('business_id', $this->business->id)->count();

    $this->get('/invoice-schemes')->assertForbidden();
    $this->withHeaders($this->ajax)->post('/invoice-schemes', esqBaseCorpo('Não grava'))->assertForbidden();
    expect(DB::table('invoice_schemes')->where('business_id', $this->business->id)->count())->toBe($antes);
});
