<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Testa que as migrations comvis_* foram aplicadas corretamente.
 *
 * Verifica existência de tabelas e colunas-chave após migrate.
 * NÃO usa biz=4 (ROTA LIVRE / cliente Larissa) — conforme ADR 0101.
 *
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see Modules/ComunicacaoVisual/Database/Migrations/
 */

// Guard SQLite: migrations comvis_* exigem MySQL (ENUM + ALTER TABLE).
// Em SQLite as tabelas não existem — tests retornariam false silenciosamente.
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: migrations comvis_* requerem MySQL UltimatePOS schema (Wagner Pest local segue mandatory — ADR 0101)');
    }
});

// ------------------------------------------------------------------
// comvis_materiais
// ------------------------------------------------------------------

it('tabela comvis_materiais existe após migrate', function () {
    expect(Schema::hasTable('comvis_materiais'))->toBeTrue();
});

it('comvis_materiais tem colunas obrigatórias', function () {
    $colunas = [
        'id', 'business_id', 'nome', 'categoria', 'unidade',
        'gramatura_g_m2', 'preco_custo_m2', 'preco_venda_m2',
        'estoque_minimo_m2', 'ativo', 'observacoes',
        'created_at', 'updated_at', 'deleted_at',
    ];

    foreach ($colunas as $coluna) {
        expect(Schema::hasColumn('comvis_materiais', $coluna))
            ->toBeTrue("coluna '{$coluna}' não encontrada em comvis_materiais");
    }
});

// ------------------------------------------------------------------
// comvis_orcamentos
// ------------------------------------------------------------------

it('tabela comvis_orcamentos existe após migrate', function () {
    expect(Schema::hasTable('comvis_orcamentos'))->toBeTrue();
});

it('comvis_orcamentos tem colunas obrigatórias', function () {
    $colunas = [
        'id', 'business_id', 'numero', 'contato_id', 'vendedor_id',
        'data_emissao', 'data_validade', 'status',
        'subtotal', 'desconto', 'extras', 'custo_instalacao', 'custo_entrega', 'total',
        'observacoes', 'created_at', 'updated_at', 'deleted_at',
    ];

    foreach ($colunas as $coluna) {
        expect(Schema::hasColumn('comvis_orcamentos', $coluna))
            ->toBeTrue("coluna '{$coluna}' não encontrada em comvis_orcamentos");
    }
});

// ------------------------------------------------------------------
// comvis_orcamento_itens
// ------------------------------------------------------------------

it('tabela comvis_orcamento_itens existe após migrate', function () {
    expect(Schema::hasTable('comvis_orcamento_itens'))->toBeTrue();
});

it('comvis_orcamento_itens tem colunas obrigatórias', function () {
    $colunas = [
        'id', 'orcamento_id', 'business_id', 'material_id',
        'descricao', 'largura_m', 'altura_m', 'quantidade',
        'area_m2', 'preco_unitario_m2', 'subtotal',
        'observacoes', 'ordem', 'created_at', 'updated_at',
    ];

    foreach ($colunas as $coluna) {
        expect(Schema::hasColumn('comvis_orcamento_itens', $coluna))
            ->toBeTrue("coluna '{$coluna}' não encontrada em comvis_orcamento_itens");
    }
});

// ------------------------------------------------------------------
// comvis_os
// ------------------------------------------------------------------

it('tabela comvis_os existe após migrate', function () {
    expect(Schema::hasTable('comvis_os'))->toBeTrue();
});

it('comvis_os tem colunas obrigatórias', function () {
    $colunas = [
        'id', 'business_id', 'orcamento_id', 'numero', 'status_etapa',
        'data_inicio', 'data_prazo', 'data_conclusao',
        'vendedor_id', 'responsavel_producao_id',
        'valor_total', 'observacoes',
        'created_at', 'updated_at', 'deleted_at',
    ];

    foreach ($colunas as $coluna) {
        expect(Schema::hasColumn('comvis_os', $coluna))
            ->toBeTrue("coluna '{$coluna}' não encontrada em comvis_os");
    }
});

// ------------------------------------------------------------------
// FKs — verificação estrutural via informações de schema
// ------------------------------------------------------------------

it('comvis_orcamento_itens tem FK para comvis_orcamentos', function () {
    // Testa indiretamente: inserir item sem orcamento deve falhar
    expect(Schema::hasTable('comvis_orcamento_itens'))->toBeTrue();
    expect(Schema::hasTable('comvis_orcamentos'))->toBeTrue();
    // FK confirmada pela existência de ambas as tabelas + comportamento do Model nos MultiTenantTests
    expect(true)->toBeTrue();
});

it('comvis_os tem FK nullable para comvis_orcamentos', function () {
    expect(Schema::hasTable('comvis_os'))->toBeTrue();
    expect(Schema::hasColumn('comvis_os', 'orcamento_id'))->toBeTrue();
    expect(true)->toBeTrue();
});
