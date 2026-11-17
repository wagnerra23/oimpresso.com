<?php

declare(strict_types=1);
// Cobre a etapa 1 da ADR 0448 (product_bom fonte única da composição): as colunas novas existem,
// com os padrões que a decisão fixou, e a ordem sem venda passa a caber em pcp_ordens.

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * ÂNCORA (contrato, não implementação): ADR 0448 §1 (regra de consumo na linha do product_bom, padrão
 * da unidade; por_quantidade padrão 1; sub_unit_id e ingredient_group_id para a cópia da etapa 2),
 * §3 (pcp_bobinas com comprimento_m e variation_id), §4 (opcional_por, a mesma chave do roteiro) e
 * §5 (pcp_ordens com venda opcional e origem; a unicidade por linha da venda continua valendo).
 *
 * Etapa 1 não muda comportamento: nenhum código lê as colunas novas. Por isso o teste só prova o
 * esquema e os padrões. Tenant 98 (ADR 0358). NUNCA biz=4.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS.');
    }
    if (! Schema::hasTable('pcp_ordens') || ! Schema::hasTable('product_bom')) {
        $this->markTestSkipped('pcp_ordens ou product_bom ausente — migrations não rodaram.');
    }
    $this->biz = (int) $this->seededTenant()->id;
});

function etapa1Coluna(string $tabela, string $coluna): ?array
{
    return collect(Schema::getColumns($tabela))->firstWhere('name', $coluna);
}

it('ADR 0448 etapa 1 · units e product_bom ganham as colunas da regra de consumo, todas opcionais', function () {
    expect(etapa1Coluna('units', 'tipo_medida'))->not->toBeNull();
    expect(etapa1Coluna('units', 'tipo_medida')['nullable'])->toBeTrue();

    foreach (['regra_consumo', 'passo_cm', 'sub_unit_id', 'ingredient_group_id', 'opcional_por'] as $coluna) {
        $definicao = etapa1Coluna('product_bom', $coluna);
        expect($definicao)->not->toBeNull();
        expect($definicao['nullable'])->toBeTrue();
    }
    expect(etapa1Coluna('product_bom', 'por_quantidade')['nullable'])->toBeFalse();
});

it('ADR 0448 etapa 1 · linha de composição sem regra nem quantidade base nasce com por_quantidade 1 e regra vazia', function () {
    $id = DB::table('product_bom')->insertGetId([
        'business_id' => $this->biz, 'parent_product_id' => 900001, 'component_product_id' => 900002,
        'qty_required' => '2.5000', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $linha = DB::table('product_bom')->where('business_id', $this->biz)->where('id', $id)->first();
    expect((string) $linha->por_quantidade)->toBe('1.0000');
    expect($linha->regra_consumo)->toBeNull();
    expect($linha->opcional_por)->toBeNull();
});

it('ADR 0448 etapa 1 · pcp_bobinas ganha comprimento e produto, ambos opcionais', function () {
    if (! Schema::hasTable('pcp_bobinas')) {
        $this->markTestSkipped('pcp_bobinas ausente.');
    }
    expect(etapa1Coluna('pcp_bobinas', 'comprimento_m')['nullable'])->toBeTrue();
    expect(etapa1Coluna('pcp_bobinas', 'variation_id')['nullable'])->toBeTrue();
});

it('ADR 0448 etapa 1 · ordem com venda segue única por linha e nasce com origem venda; ordem sem venda passa a caber', function () {
    $ordem = fn (array $extra) => DB::table('pcp_ordens')->insertGetId(array_merge([
        'business_id' => $this->biz, 'product_id' => 900001, 'quantidade' => '1.0000',
        'created_at' => now(), 'updated_at' => now(),
    ], $extra));

    $comVenda = $ordem(['transaction_id' => 910001, 'transaction_sell_line_id' => 920001]);
    expect(DB::table('pcp_ordens')->where('id', $comVenda)->value('origem'))->toBe('venda');

    // Duas ordens para estoque na mesma empresa: sem venda, não há linha para colidir.
    $estoque1 = $ordem(['transaction_id' => null, 'transaction_sell_line_id' => null, 'origem' => 'estoque']);
    $estoque2 = $ordem(['transaction_id' => null, 'transaction_sell_line_id' => null, 'origem' => 'estoque']);
    expect($estoque2)->not->toBe($estoque1);

    // A mesma linha da venda duas vezes na mesma empresa continua recusada.
    expect(fn () => $ordem(['transaction_id' => 910001, 'transaction_sell_line_id' => 920001]))
        ->toThrow(QueryException::class);
});
