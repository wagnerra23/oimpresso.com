<?php

declare(strict_types=1);

// @covers-us US-NFE-010 — a Jana lê o cadastro fiscal sem PII (D-IA, playbook Fiscal thread 10).
// Contrato da tela: resources/js/Pages/NfeBrasil/Tributacao/Index.casos.md — UC-NFTR-11

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Ai\Tools\Fiscal\ProdutoFiscalTool;

uses(Tests\TestCase::class);

/** MySQL-only · tenant 98 e vizinho 99 (ADR 0358). SKUs PFT10-* reservados a este arquivo. */

function pftLimpar(): void
{
    DB::table('products')->where('sku', 'like', 'PFT10-%')->delete();
}

function pftProduto(int $biz, string $sku, int $userId): int
{
    $unit = (int) (DB::table('units')->where('business_id', $biz)->value('id')
        ?? DB::table('units')->insertGetId([
            'business_id' => $biz, 'actual_name' => 'Unidade tool', 'short_name' => 'un',
            'allow_decimal' => 0, 'created_by' => $userId, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
        ]));

    return (int) DB::table('products')->insertGetId([
        'business_id' => $biz, 'name' => 'Produto ' . $sku, 'type' => 'single', 'unit_id' => $unit, 'sku' => $sku,
        'ncm' => '77091001', 'product_description' => '<p>Lona 440g</p>', 'enable_stock' => 0, 'alert_quantity' => 0,
        'tax_type' => 'exclusive', 'barcode_type' => 'C128', 'created_by' => $userId,
        'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
    ]);
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasColumn('products', 'ncm')) {
        $this->markTestSkipped('MySQL-only: lê products reais.');
    }
    pftLimpar();
});

afterEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        pftLimpar();
    }
});

it('UC-NFTR-11 · tool fiscal só lê o próprio tenant e sem PII', function () {
    $u = test()->usuarioComPermissoes([]);
    $meu    = pftProduto(test()->seededTenant()->id, 'PFT10-MEU', $u->id);
    $alheio = pftProduto(test()->seededSupportClientTenant()->id, 'PFT10-ALHEIO', $u->id);

    $tool  = new ProdutoFiscalTool(test()->seededTenant()->id);
    $dados = $tool->dados($meu);

    expect($dados)->toHaveCount(1)
        ->and(array_keys($dados[0]))->toBe(['id', 'nome', 'descricao', 'unidade', 'categoria', 'ncm', 'regras'])
        ->and($dados[0]['id'])->toBe($meu)
        ->and($dados[0]['descricao'])->toBe('Lona 440g'); // HTML removido

    // Nada de preço, custo, cliente nem CNPJ no que vai para o LLM.
    $json = json_encode($dados);
    foreach (['price', 'preco', 'cost', 'custo', 'contact', 'cliente', 'cnpj', 'tax_number'] as $proibido) {
        expect(stripos($json, $proibido))->toBeFalse();
    }

    // O produto de outra empresa não aparece, nem pedindo pelo id dele.
    expect($tool->dados($alheio))->toBe([])
        ->and(collect($tool->dados(null, 100))->pluck('id')->all())->not->toContain($alheio);

    // CONTROLE POSITIVO — a tool da outra empresa vê o produto dela.
    expect((new ProdutoFiscalTool(test()->seededSupportClientTenant()->id))->dados($alheio))->toHaveCount(1);
});
