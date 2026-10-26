<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\EstoqueFixture;

/**
 * US-COM-003 fase 1 — schema da entrada por XML de compra.
 *
 * Contrato: ADR `memory/decisions/proposals/2026-10-06-entrada-xml-compra.md` D2/D3 e as regras
 * R-COM-204/205 do SPEC de Compras. Não testa a forma das colunas, testa o que elas garantem:
 * - o de-para é único por (empresa, CNPJ, código) e não colide entre empresas;
 * - uma compra só se liga a uma nota da mesma empresa (reimportação idempotente);
 * - nota vinda de upload entra sem NSU.
 *
 * Tenant 98 (ADR 0358) e adversário 99 ou o biz=2 do seed, nunca 1 nem 4.
 *
 * @covers-us US-COM-003
 */
uses(Tests\TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || ! \Schema::hasTable('compras_produto_fornecedor')) {
        $this->markTestSkipped('Schema MySQL com as migrations da fase 1 ausente — rode no CT 100 / lane compras-pest.');
    }
    $this->biz = EstoqueFixture::businessId();
    $this->outra = (int) DB::table('business')->whereIn('id', [99, 2])->where('id', '!=', $this->biz)
        ->orderByDesc('id')->value('id');
    if ($this->outra <= 0) {
        $this->markTestSkipped('Sem empresa adversária fictícia (99 ou 2) neste ambiente.');
    }
    $this->varA = EstoqueFixture::singleProduct($this->biz)->variationId();
    $this->varB = EstoqueFixture::singleProduct($this->outra)->variationId();
});

function exfDePara(int $biz, int $variation, string $codigo = 'ABC-1'): void
{
    DB::table('compras_produto_fornecedor')->insert([
        'business_id' => $biz,
        'cnpj_fornecedor' => '11222333000181',
        'codigo_fornecedor' => $codigo,
        'variation_id' => $variation,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function exfNota(int $biz, ?int $nsu, string $origem = 'nsu', ?int $tx = null): int
{
    return DB::table('nfe_dfe_recebidos')->insertGetId([
        'business_id' => $biz,
        'chave_44' => str_pad((string) random_int(1, PHP_INT_MAX), 44, '0', STR_PAD_LEFT),
        'nsu' => $nsu,
        'origem' => $origem,
        'transaction_id' => $tx,
        'cnpj_emitente' => '11222333000181',
        'data_emissao' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('o mesmo código do fornecedor vale uma vez por empresa e não colide entre empresas', function () {
    exfDePara($this->biz, $this->varA);
    exfDePara($this->outra, $this->varB);

    expect(DB::table('compras_produto_fornecedor')->where('codigo_fornecedor', 'ABC-1')
        ->whereIn('business_id', [$this->biz, $this->outra])->count())->toBe(2);

    expect(fn () => exfDePara($this->biz, $this->varA))->toThrow(QueryException::class);
});

it('uma compra se liga a uma nota só da empresa; notas sem compra repetem', function () {
    $tx = DB::table('transactions')->insertGetId([
        'business_id' => $this->biz,
        'type' => 'purchase',
        'status' => 'received',
        'transaction_date' => now(),
        'created_by' => EstoqueFixture::userId($this->biz),
        'essentials_duration' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    exfNota($this->biz, 1, 'nsu', $tx);
    exfNota($this->biz, 2);
    exfNota($this->biz, 3);

    expect(fn () => exfNota($this->biz, 4, 'nsu', $tx))->toThrow(QueryException::class);
});

it('nota de upload entra sem NSU e grava a origem', function () {
    $id = exfNota($this->biz, null, 'upload');

    $row = DB::table('nfe_dfe_recebidos')->where('id', $id)->first();
    expect($row->nsu)->toBeNull();
    expect($row->origem)->toBe('upload');
});
