<?php
// @covers-us US-PROD-024

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * UC-VSHOW-24/25/26 · US-PROD-024 fase 2 — a venda finalizada mostra a margem pelo custo médio
 * vigente na data da venda, só para quem vê custo e só com a flag ligada.
 *
 * Âncora: resources/js/Pages/Sells/Show.casos.md UC-VSHOW-24..26 + SPEC Produto US-PROD-024.
 * Números feitos à mão (regra mestre de valor: caminho A aqui, caminho B no controller):
 *
 *   venda finalizada em 2026-10-20 10:00 · linha 3 un × 20,00 = receita 60,00
 *   custo médio da variação: 15,00 registrado em 2026-10-19 · 30,00 só em 2026-10-25
 *   vale o de 19/10 → custo 3 × 15 = 45,00 · margem 15,00 · 15 / 60 = 25,0%
 *   linha: (20 − 15) / 20 = 25,0%
 *   segundo item (1 un × 40,00) sem custo médio → fora da conta, itens_sem_custo = 1
 */
uses(DatabaseTransactions::class, \Tests\Support\WithSeededTenant::class);

function margemCmGet(object $test, int $saleId)
{
    $manifest = public_path('build-inertia/manifest.json');

    return $test->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => file_exists($manifest) ? md5_file($manifest) : '1',
        'X-Inertia-Partial-Component' => 'Sells/Show',
        'X-Inertia-Partial-Data' => 'detail',
    ])->get("/sells/{$saleId}");
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || ! Schema::hasTable('compras_custo_medio_movimentos')) {
        $this->markTestSkipped('Schema UltimatePOS/Compras ausente — roda na lane MySQL.');
    }

    $this->bizId = EstoqueFixture::businessId();
    $biz = \App\Business::findOrFail($this->bizId);
    Permission::findOrCreate('view_purchase_price', 'web');
    $this->semCusto = $this->usuarioComPermissoes(['sell.view'], $biz);
    $this->comCusto = $this->usuarioComPermissoes(['sell.view', 'view_purchase_price'], $biz);
    $this->entrar = function (User $u) {
        $this->actingAs($u);
        session(['user.business_id' => $this->bizId, 'user.id' => $u->id]);
    };
    config(['compras.custo_medio_movel' => true]);

    $lona = EstoqueFixture::singleProduct($this->bizId);
    $servico = EstoqueFixture::singleProduct($this->bizId);
    $local = EstoqueFixture::locationId($this->bizId);
    $venda = EstoqueFixture::saleWithLine($lona, 0, $local, 3.0, 20.0);
    $this->saleId = $venda['transaction_id'];
    $this->lineId = $venda['sell_line_id'];
    $this->variationId = $lona->variations[0]['variation_id'];
    DB::table('transactions')->where('id', $this->saleId)->update(['transaction_date' => '2026-10-20 10:00:00']);
    $this->linhaSemCusto = DB::table('transaction_sell_lines')->insertGetId([
        'transaction_id' => $this->saleId, 'product_id' => $servico->productId, 'variation_id' => $servico->variations[0]['variation_id'],
        'quantity' => 1, 'quantity_returned' => 0, 'unit_price' => 40, 'unit_price_inc_tax' => 40, 'item_tax' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->movimento = function (int $biz, float $custo, string $em) {
        DB::table('compras_custo_medio_movimentos')->insert([
            'business_id' => $biz, 'variation_id' => $this->variationId, 'transaction_id' => null, 'origem' => 'semente',
            'qtd_antes' => 0, 'custo_antes' => null, 'qtd_entrada' => 0, 'custo_entrada' => null, 'custo_depois' => $custo,
            'registrado_em' => $em, 'created_at' => now(), 'updated_at' => now(),
        ]);
    };
});

it('UC-VSHOW-24 · venda finalizada traz a margem pelo custo médio vigente na data da venda', function () {
    ($this->movimento)($this->bizId, 15.00, '2026-10-19 08:00:00');
    ($this->movimento)($this->bizId, 30.00, '2026-10-25 08:00:00');
    ($this->entrar)($this->comCusto);

    $r = margemCmGet($this, $this->saleId);
    $r->assertStatus(200);
    $margem = json_decode($r->getContent(), true)['props']['detail']['margem'];

    $linha = collect($margem['linhas'])->firstWhere('id', $this->lineId);
    $sem = collect($margem['linhas'])->firstWhere('id', $this->linhaSemCusto);
    expect((float) $margem['receita'])->toBe(60.0)
        ->and((float) $margem['custo'])->toBe(45.0)
        ->and((float) $margem['margem'])->toBe(15.0)
        ->and((float) $margem['margem_pct'])->toBe(25.0)
        ->and($margem['itens_sem_custo'])->toBe(1)
        ->and((float) $linha['custo_medio'])->toBe(15.0)
        ->and((float) $linha['margem_pct'])->toBe(25.0)
        ->and($sem['custo_medio'])->toBeNull();
});

it('UC-VSHOW-25 · sem view_purchase_price, com a flag desligada ou em orçamento, a margem não vai ao navegador', function () {
    ($this->movimento)($this->bizId, 15.00, '2026-10-19 08:00:00');

    ($this->entrar)($this->semCusto);
    $r = margemCmGet($this, $this->saleId);
    $r->assertStatus(200);
    expect(json_decode($r->getContent(), true)['props']['detail']['margem'])->toBeNull();
    expect($r->getContent())->not->toContain('itens_sem_custo');

    ($this->entrar)($this->comCusto);
    config(['compras.custo_medio_movel' => false]);
    expect(json_decode(margemCmGet($this, $this->saleId)->getContent(), true)['props']['detail']['margem'])->toBeNull();

    config(['compras.custo_medio_movel' => true]);
    DB::table('transactions')->where('id', $this->saleId)->update(['status' => 'draft']);
    expect(json_decode(margemCmGet($this, $this->saleId)->getContent(), true)['props']['detail']['margem'])->toBeNull();
});

it('UC-VSHOW-26 · o custo médio de outra empresa não entra na margem', function () {
    $outra = $this->seededSupportClientTenant();
    ($this->movimento)((int) $outra->id, 15.00, '2026-10-19 08:00:00');
    ($this->entrar)($this->comCusto);

    $margem = json_decode(margemCmGet($this, $this->saleId)->getContent(), true)['props']['detail']['margem'];

    expect($margem['itens_sem_custo'])->toBe(2)
        ->and((float) $margem['receita'])->toBe(0.0)
        ->and($margem['margem_pct'])->toBeNull();
});
