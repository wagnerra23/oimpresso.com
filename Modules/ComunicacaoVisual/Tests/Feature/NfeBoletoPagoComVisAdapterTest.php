<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\ComunicacaoVisual\Entities\OrdemProducao;
use Modules\ComunicacaoVisual\Entities\Substrato;
use Modules\ComunicacaoVisual\Services\NfeBoletoPagoComVisAdapter;

uses(Tests\TestCase::class);

/**
 * NfeBoletoPagoComVisAdapter — decide doc fiscal pós-boleto-pago (US-COMVIS-009).
 *
 * Cenários cobertos:
 *   1. cliente_busca + total<200 + sem contato → NFC-e
 *   2. cliente_busca + total≥200 ou com contato → NFe single
 *   3. entrega_apenas → NFe single
 *   4. fachada_simples com material+serviço → DUAL (NFe55 + NFSe56)
 *   5. fachada_andaime/nr35 → DUAL + alerta NR-35
 *   6. fachada_* sem material (100% serviço) → NFSe pura
 *   7. tipo desconhecido → fallback NFe + alerta
 *   8. total = 0 → throw
 *
 * Substrato com NCM/CFOP/CSOSN preenchidos respeitado no doc fiscal.
 *
 * @see Modules\ComunicacaoVisual\Services\NfeBoletoPagoComVisAdapter
 */

const NFE_BIZ = 1;

beforeEach(function () {
    if (! Schema::hasTable('cv_substratos') || ! Schema::hasTable('cv_ordens_producao')) {
        $this->markTestSkipped('cv_* tables missing — rode migrate primeiro.');
    }
    session(['user.business_id' => NFE_BIZ]);
});

afterEach(function () {
    OrdemProducao::withoutGlobalScopes()
        ->where('codigo', 'like', 'OP-ADAPT-%')->forceDelete();
    Substrato::withoutGlobalScopes()
        ->where('nome', 'like', 'Lona Adapter%')->forceDelete();
});

function criarSubstratoCanon(array $overrides = []): Substrato
{
    return Substrato::withoutGlobalScopes()->create(array_merge([
        'business_id'    => NFE_BIZ,
        'nome'           => 'Lona Adapter Test',
        'categoria'      => 'lona',
        'preco_venda_m2' => 28.00,
        'ncm'            => '3920.20',
        'cfop_padrao'    => '5101',
        'csosn_padrao'   => '102',
        'ativo'          => true,
    ], $overrides));
}

function criarOrdem(array $overrides = []): OrdemProducao
{
    return OrdemProducao::withoutGlobalScopes()->create(array_merge([
        'business_id'     => NFE_BIZ,
        'codigo'          => 'OP-ADAPT-' . random_int(10000, 99999),
        'qtd'             => 1,
        'instalacao_tipo' => 'cliente_busca',
        'subtotal'        => 0,
        'total'           => 0,
    ], $overrides));
}

// ─────────────────────────────────────────────────────────────────────────────
// Cenário 1: NFC-e — varejo balcão simples
// ─────────────────────────────────────────────────────────────────────────────

it('NFC-e: cliente_busca + total<200 + sem contato → mod 65', function () {
    $sub = criarSubstratoCanon();
    $op  = criarOrdem([
        'substrato_id'    => $sub->id,
        'instalacao_tipo' => 'cliente_busca',
        'contato_id'      => null,
        'subtotal'        => 120.00,
        'total'           => 120.00,
    ]);

    $r = (new NfeBoletoPagoComVisAdapter())->decide($op);

    expect($r['mode'])->toBe('single');
    expect($r['docs'])->toHaveCount(1);
    expect($r['docs'][0]['tipo'])->toBe('nfce65');
    expect($r['docs'][0]['ncm'])->toBe('3920.20');
    expect($r['docs'][0]['cfop'])->toBe('5102'); // NFC-e revenda
    expect($r['docs'][0]['valor'])->toBe(120.00);
});

// ─────────────────────────────────────────────────────────────────────────────
// Cenário 2: NFe single — venda mercadoria
// ─────────────────────────────────────────────────────────────────────────────

it('NFe single: cliente_busca + total alto → mod 55 com NCM/CFOP/CSOSN do substrato', function () {
    $sub = criarSubstratoCanon();
    $op  = criarOrdem([
        'substrato_id'    => $sub->id,
        'instalacao_tipo' => 'cliente_busca',
        'subtotal'        => 500.00,
        'total'           => 500.00,
    ]);

    $r = (new NfeBoletoPagoComVisAdapter())->decide($op);

    expect($r['mode'])->toBe('single');
    expect($r['docs'][0]['tipo'])->toBe('nfe55');
    expect($r['docs'][0]['ncm'])->toBe('3920.20');
    expect($r['docs'][0]['cfop'])->toBe('5101'); // produção própria
    expect($r['docs'][0]['csosn'])->toBe('102');
    expect($r['docs'][0]['valor'])->toBe(500.00);
});

it('NFe single: cliente_busca + total baixo MAS com contato → NFe não NFC-e', function () {
    $sub = criarSubstratoCanon();
    $op  = criarOrdem([
        'substrato_id'    => $sub->id,
        'instalacao_tipo' => 'cliente_busca',
        'contato_id'      => 999, // com cliente identificado
        'subtotal'        => 80.00,
        'total'           => 80.00,
    ]);

    $r = (new NfeBoletoPagoComVisAdapter())->decide($op);
    expect($r['docs'][0]['tipo'])->toBe('nfe55'); // contato identificado força NFe
});

it('NFe single: entrega_apenas (venda sem instalação)', function () {
    $sub = criarSubstratoCanon();
    $op  = criarOrdem([
        'substrato_id'    => $sub->id,
        'instalacao_tipo' => 'entrega_apenas',
        'subtotal'        => 300.00,
        'total'           => 300.00,
    ]);

    $r = (new NfeBoletoPagoComVisAdapter())->decide($op);
    expect($r['docs'][0]['tipo'])->toBe('nfe55');
});

// ─────────────────────────────────────────────────────────────────────────────
// Cenário 3: DUAL — material + serviço (fachada com instalação)
// ─────────────────────────────────────────────────────────────────────────────

it('DUAL: fachada_simples com subtotal+extras → NFe55 (material) + NFSe56 (serviço)', function () {
    $sub = criarSubstratoCanon();
    $op  = criarOrdem([
        'substrato_id'    => $sub->id,
        'instalacao_tipo' => 'fachada_simples',
        'subtotal'        => 400.00,
        'extras'          => 100.00,
        'total'           => 500.00,
    ]);

    $r = (new NfeBoletoPagoComVisAdapter())->decide($op);

    expect($r['mode'])->toBe('dual');
    expect($r['docs'])->toHaveCount(2);

    // NFe55 do material
    expect($r['docs'][0]['tipo'])->toBe('nfe55');
    expect($r['docs'][0]['ncm'])->toBe('3920.20');
    expect($r['docs'][0]['valor'])->toBe(400.00);

    // NFSe56 do serviço
    expect($r['docs'][1]['tipo'])->toBe('nfse56');
    expect($r['docs'][1]['item_ls'])->toBe('24.01');
    expect($r['docs'][1]['valor'])->toBe(100.00);
});

it('DUAL: fachada_nr35 emite NFe + NFSe + alerta NR-35 documentos', function () {
    $sub = criarSubstratoCanon();
    $op  = criarOrdem([
        'substrato_id'    => $sub->id,
        'instalacao_tipo' => 'fachada_nr35',
        'subtotal'        => 800.00,
        'extras'          => 0,
        'total'           => 1000.00, // total - subtotal = 200 serviço
    ]);

    $r = (new NfeBoletoPagoComVisAdapter())->decide($op);

    expect($r['mode'])->toBe('dual');
    expect($r['docs'])->toHaveCount(2);
    expect($r['alertas'])->toHaveCount(1);
    expect($r['alertas'][0])->toContain('NR-35');
});

// ─────────────────────────────────────────────────────────────────────────────
// Cenário 4: NFSe pura — serviço sem material
// ─────────────────────────────────────────────────────────────────────────────

it('NFSe pura: fachada_simples sem material vendido → só NFSe56', function () {
    // Sem substrato (subtotal=0, total=serviço puro)
    $op = criarOrdem([
        'instalacao_tipo' => 'fachada_simples',
        'subtotal'        => 0,
        'extras'          => 0,
        'total'           => 350.00,
    ]);

    $r = (new NfeBoletoPagoComVisAdapter())->decide($op);

    expect($r['mode'])->toBe('single');
    expect($r['docs'])->toHaveCount(1);
    expect($r['docs'][0]['tipo'])->toBe('nfse56');
    expect($r['docs'][0]['item_ls'])->toBe('24.01');
    expect($r['docs'][0]['valor'])->toBe(350.00);
});

// ─────────────────────────────────────────────────────────────────────────────
// Cenário 5: Edge - fallback + throw
// ─────────────────────────────────────────────────────────────────────────────

it('Throw: total = 0 não pode decidir doc fiscal', function () {
    $op = criarOrdem(['total' => 0]);

    expect(fn () => (new NfeBoletoPagoComVisAdapter())->decide($op))
        ->toThrow(\InvalidArgumentException::class, 'total deve ser > 0');
});

it('Fallback: substrato ausente → usa NCM 4911.10 + CFOP 5101 padrão', function () {
    $op = criarOrdem([
        'instalacao_tipo' => 'cliente_busca',
        'substrato_id'    => null,
        'contato_id'      => 999,
        'subtotal'        => 250.00,
        'total'           => 250.00,
    ]);

    $r = (new NfeBoletoPagoComVisAdapter())->decide($op);
    expect($r['docs'][0]['ncm'])->toBe('4911.10');
    expect($r['docs'][0]['cfop'])->toBe('5101');
});
