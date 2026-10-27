<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\AssetManagement\Services\DepreciacaoGerencial;
use Tests\Support\EstoqueFixture;

/**
 * US-ASSET-009 — depreciação gerencial linear (decisão [W] 2026-10-26).
 *
 * Contrato (SPEC AssetManagement US-ASSET-009): linear pela vida útil em anos do bem;
 * residual = valor de compra × (meses que faltam ÷ meses totais); bem sem vida útil fica fora.
 *
 * Regra mestre (valor exibido): esperados escritos À MÃO (caminho A) × serviço lendo `assets`
 * (caminho B). Hoje = 02/11/2026.
 *   Plotter  12.000,00 × 1, 10 anos, compra 01/01/2024 → 1.036 dias ÷ 30,44 = 34 meses de 120
 *            → 12.000,00 × 86/120 = 8.600,00
 *   Notebook  3.000,00 × 2,  5 anos, compra 01/01/2020 → passou dos 60 meses → 0,00
 *   Mesa        800,00 × 1, sem vida útil → fora da conta (sem_prazo = 1)
 *   residual do patrimônio = 8.600,00 · com_prazo = 2 · sem_prazo = 1
 *
 * Tenant 98 (ADR 0358); adversário 99 ou o biz=2 do seed, nunca 1 nem 4.
 *
 * @covers-us US-ASSET-009
 * @covers-uc UC-PAT-12 — resources/js/Pages/Patrimonio/Index.casos.md
 */
uses(Tests\TestCase::class, DatabaseTransactions::class);

function depBem(int $biz, string $nome, string $preco, string $qtd, ?string $anos, ?string $compra): void
{
    DB::table('assets')->insert([
        'business_id' => $biz, 'asset_code' => 'DEP-' . uniqid(), 'name' => $nome,
        'quantity' => $qtd, 'unit_price' => $preco, 'depreciation' => $anos, 'purchase_date' => $compra,
        'created_by' => EstoqueFixture::userId($biz), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || ! \Schema::hasTable('assets')) {
        $this->markTestSkipped('Schema MySQL do Patrimônio ausente — rode no CT 100 / lane assetmanagement-pest.');
    }
    $this->hoje = Carbon::parse('2026-11-02');
});

it('o residual de um bem segue a conta linear escrita à mão', function () {
    expect(DepreciacaoGerencial::residualCentavos('12000.0000', '1.0000', '10', '2024-01-01', $this->hoje))->toBe(860000);
    expect(DepreciacaoGerencial::residualCentavos('3000.0000', '2.0000', '5', '2020-01-01', $this->hoje))->toBe(0);
    expect(DepreciacaoGerencial::residualCentavos('800.0000', '1.0000', null, '2025-01-01', $this->hoje))->toBeNull();
    expect(DepreciacaoGerencial::residualCentavos('800.0000', '1.0000', '10', null, $this->hoje))->toBeNull();
    // Compra no futuro não deprecia nada ainda.
    expect(DepreciacaoGerencial::residualCentavos('100.0000', '1.0000', '1', '2027-01-01', $this->hoje))->toBe(10000);
});

it('o patrimônio da empresa soma só os bens com vida útil, e só os dela', function () {
    $biz = EstoqueFixture::businessId();
    $svc = app(DepreciacaoGerencial::class);
    // Diferença antes → depois: o seed pode já ter bens nesta empresa, e o teste não os apaga.
    $antes = $svc->doPatrimonio($biz, $this->hoje);

    depBem($biz, 'Plotter', '12000.0000', '1.0000', '10', '2024-01-01');
    depBem($biz, 'Notebook', '3000.0000', '2.0000', '5', '2020-01-01');
    depBem($biz, 'Mesa', '800.0000', '1.0000', null, '2025-01-01');
    $outra = (int) DB::table('business')->whereIn('id', [99, 2])->where('id', '!=', $biz)->orderByDesc('id')->value('id');
    if ($outra > 0) {
        depBem($outra, 'Bem alheio', '99999.0000', '1.0000', '10', '2026-10-01');
    }

    $depois = $svc->doPatrimonio($biz, $this->hoje);
    expect(round(($depois['residual'] ?? 0) - ($antes['residual'] ?? 0), 2))->toBe(8600.0);
    expect($depois['com_prazo'] - $antes['com_prazo'])->toBe(2);
    expect($depois['sem_prazo'] - $antes['sem_prazo'])->toBe(1);
});

it('sem nenhum bem com vida útil, o residual é null (o painel mostra o traço)', function () {
    $biz = EstoqueFixture::businessId();
    if (app(DepreciacaoGerencial::class)->doPatrimonio($biz, $this->hoje)['com_prazo'] > 0) {
        $this->markTestSkipped('O seed já tem bem com vida útil nesta empresa.');
    }
    depBem($biz, 'Mesa', '800.0000', '1.0000', null, '2025-01-01');

    expect(app(DepreciacaoGerencial::class)->doPatrimonio($biz, $this->hoje)['residual'])->toBeNull();
});
