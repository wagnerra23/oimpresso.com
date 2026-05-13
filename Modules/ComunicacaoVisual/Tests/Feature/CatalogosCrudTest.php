<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\ComunicacaoVisual\Entities\Acabamento;
use Modules\ComunicacaoVisual\Entities\InstalacaoCatalogo;
use Modules\ComunicacaoVisual\Entities\Substrato;

uses(Tests\TestCase::class);

/**
 * CRUD REST catálogos canônicos cv_* (ROADMAP Fase 2 §2.3).
 *
 * Cobre:
 *   - Substrato CRUD (categoria enum + tributação NCM/CFOP/CSOSN)
 *   - Acabamento CRUD (tipo enum m_linear/unitario/m2/fixo)
 *   - InstalacaoCatalogo CRUD (preco_base/m2/km + exige_nr35 + ferramentas_json)
 *
 * Multi-tenant Tier 0: cada teste seta session biz=1 (ADR 0101).
 *
 * @see Modules\ComunicacaoVisual\Http\Controllers\SubstratoController
 * @see Modules\ComunicacaoVisual\Http\Controllers\AcabamentoController
 * @see Modules\ComunicacaoVisual\Http\Controllers\InstalacaoCatalogoController
 */

const CRUD_BIZ = 1;

beforeEach(function () {
    if (! Schema::hasTable('cv_substratos')) {
        $this->markTestSkipped('cv_* tables missing — rode migrate primeiro.');
    }
    session(['user.business_id' => CRUD_BIZ]);
});

// ──────────────────────────────────────────────────────────────────────────────
// SUBSTRATO
// ──────────────────────────────────────────────────────────────────────────────

it('Substrato CRUD: store + show + update + destroy', function () {
    // STORE
    $created = Substrato::create([
        'nome'           => 'Lona CRUD Test',
        'categoria'      => 'lona',
        'gramatura_g_m2' => 440,
        'preco_venda_m2' => 28.00,
        'ncm'            => '4911.10',
        'cfop_padrao'    => '5101',
        'csosn_padrao'   => '102',
        'ativo'          => true,
    ]);
    expect($created->business_id)->toBe(CRUD_BIZ); // global scope auto-popula

    // SHOW
    $found = Substrato::find($created->id);
    expect($found->nome)->toBe('Lona CRUD Test');
    expect($found->categoria)->toBe('lona');
    expect((float) $found->preco_venda_m2)->toBe(28.00);

    // UPDATE
    $found->update(['preco_venda_m2' => 32.00]);
    expect((float) $found->fresh()->preco_venda_m2)->toBe(32.00);

    // DESTROY (soft)
    $found->delete();
    expect(Substrato::find($created->id))->toBeNull();
    expect(Substrato::withTrashed()->find($created->id))->not->toBeNull();

    Substrato::withoutGlobalScopes()->where('id', $created->id)->forceDelete();
});

it('Substrato scope categoria filtra corretamente', function () {
    Substrato::create(['nome' => 'Lona X', 'categoria' => 'lona', 'preco_venda_m2' => 20.00]);
    Substrato::create(['nome' => 'Vinil Y', 'categoria' => 'vinil', 'preco_venda_m2' => 35.00]);

    $lonas = Substrato::categoria('lona')->get();
    expect($lonas->pluck('nome'))->toContain('Lona X');
    expect($lonas->pluck('nome'))->not->toContain('Vinil Y');

    Substrato::withoutGlobalScopes()->whereIn('nome', ['Lona X', 'Vinil Y'])->forceDelete();
});

// ──────────────────────────────────────────────────────────────────────────────
// ACABAMENTO
// ──────────────────────────────────────────────────────────────────────────────

it('Acabamento CRUD: 4 tipos enum (m_linear / unitario / m2 / fixo)', function () {
    $bainha = Acabamento::create(['nome' => 'Bainha CRUD',   'tipo' => 'm_linear', 'preco' => 8.00, 'ativo' => true]);
    $ilhos  = Acabamento::create(['nome' => 'Ilhós CRUD',    'tipo' => 'unitario', 'preco' => 1.50, 'ativo' => true]);
    $lamin  = Acabamento::create(['nome' => 'Laminação CRUD','tipo' => 'm2',       'preco' => 15.00, 'ativo' => true]);
    $setup  = Acabamento::create(['nome' => 'Setup CRUD',    'tipo' => 'fixo',     'preco' => 50.00, 'ativo' => true]);

    expect($bainha->business_id)->toBe(CRUD_BIZ);
    expect($ilhos->tipo)->toBe('unitario');
    expect((float) $lamin->preco)->toBe(15.00);

    foreach ([$bainha, $ilhos, $lamin, $setup] as $a) {
        Acabamento::withoutGlobalScopes()->where('id', $a->id)->forceDelete();
    }
});

it('Acabamento scope ativos filtra inativos', function () {
    $a1 = Acabamento::create(['nome' => 'Ativo A', 'tipo' => 'fixo', 'preco' => 10.00, 'ativo' => true]);
    $a2 = Acabamento::create(['nome' => 'Inativo A', 'tipo' => 'fixo', 'preco' => 10.00, 'ativo' => false]);

    $ativos = Acabamento::ativos()->whereIn('id', [$a1->id, $a2->id])->get();
    expect($ativos)->toHaveCount(1);
    expect($ativos->first()->nome)->toBe('Ativo A');

    Acabamento::withoutGlobalScopes()->whereIn('id', [$a1->id, $a2->id])->forceDelete();
});

// ──────────────────────────────────────────────────────────────────────────────
// INSTALACAO_CATALOGO
// ──────────────────────────────────────────────────────────────────────────────

it('InstalacaoCatalogo CRUD com exige_nr35 + ferramentas_json', function () {
    $cat = InstalacaoCatalogo::create([
        'nome'                         => 'Fachada Andaime CRUD',
        'preco_base'                   => 350.00,
        'preco_m2'                     => 15.00,
        'preco_km'                     => 2.00,
        'exige_nr35'                   => true,
        'ferramentas_necessarias_json' => ['Andaime', 'Cinto NBR 15834', 'Linha vida', 'Talabarte duplo'],
        'ativo'                        => true,
    ]);

    expect($cat->business_id)->toBe(CRUD_BIZ);
    expect((float) $cat->preco_base)->toBe(350.00);
    expect($cat->exige_nr35)->toBeTrue();
    expect($cat->ferramentas_necessarias_json)->toBeArray();
    expect($cat->ferramentas_necessarias_json)->toContain('Andaime');

    InstalacaoCatalogo::withoutGlobalScopes()->where('id', $cat->id)->forceDelete();
});

it('InstalacaoCatalogo scope ativos', function () {
    $c1 = InstalacaoCatalogo::create(['nome' => 'Ativo I', 'preco_base' => 100, 'ativo' => true]);
    $c2 = InstalacaoCatalogo::create(['nome' => 'Inativo I', 'preco_base' => 100, 'ativo' => false]);

    $ativos = InstalacaoCatalogo::ativos()->whereIn('id', [$c1->id, $c2->id])->get();
    expect($ativos)->toHaveCount(1);

    InstalacaoCatalogo::withoutGlobalScopes()->whereIn('id', [$c1->id, $c2->id])->forceDelete();
});
