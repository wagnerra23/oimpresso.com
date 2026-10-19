<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * UC-VSHOW-08 · US-SELL-068 · UC-FLX-34 — o orçamento aberto mostra o custo guardado ao lado
 * do custo de hoje e a margem que sobra, só para quem pode ver custo.
 *
 * Âncora: resources/js/Pages/Sells/Show.casos.md UC-VSHOW-08 + SPEC US-SELL-068 + catálogo da
 * tela de fluxos (UC-FLX-34). Números feitos à mão:
 *
 *   linha: 2 un × 50,00 = receita 100,00 · custo guardado 20,00 (dia do orçamento)
 *   compra recebida hoje: 10 un × 30,00 → custo de hoje 30,00 (único lote recebido)
 *   total guardado = 2 × 20 = 40,00 · total hoje = 2 × 30 = 60,00
 *   margem prevista = (100 − 40) / 100 = 60,0% · margem hoje = (100 − 60) / 100 = 40,0%
 *   |30 − 20| / 20 = 50% > 5% → a linha "mudou"
 */
uses(DatabaseTransactions::class, \Tests\Support\WithSeededTenant::class);

function custoOrcGet(object $test, int $saleId)
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
    if (! EstoqueFixture::schemaReady() || ! Schema::hasColumn('transaction_sell_lines', 'custo_previsto')) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente — roda na lane MySQL / CT 100.');
    }

    $this->bizId = EstoqueFixture::businessId();
    $biz = \App\Business::findOrFail($this->bizId);
    // Usuários NOVOS com permissões exatas: o primeiro usuário do business pode ser admin, e
    // o Gate::before liberaria `view_purchase_price` — o caso "sem permissão" passaria por engano.
    Permission::findOrCreate('view_purchase_price', 'web');
    $this->semCusto = $this->usuarioComPermissoes(['sell.view'], $biz);
    $this->comCusto = $this->usuarioComPermissoes(['sell.view', 'view_purchase_price'], $biz);
    $this->user = $this->semCusto;
    $this->entrar = function (User $u) {
        $this->actingAs($u);
        session(['user.business_id' => $this->bizId, 'user.id' => $u->id]);
    };
    ($this->entrar)($this->semCusto);

    $produto = EstoqueFixture::singleProduct($this->bizId);
    $venda = EstoqueFixture::saleWithLine($produto, 0, EstoqueFixture::locationId($this->bizId), 2.0, 50.0);
    $this->saleId = $venda['transaction_id'];
    $this->lineId = $venda['sell_line_id'];
    $variationId = $produto->variations[0]['variation_id'];

    DB::table('transactions')->where('id', $this->saleId)->update(['status' => 'draft']);
    DB::table('transaction_sell_lines')->where('id', $this->lineId)->update([
        'custo_previsto' => 20, 'custo_previsto_em' => '2026-03-15', 'custo_previsto_metodo' => 'medio',
    ]);

    $compraId = DB::table('transactions')->insertGetId([
        'business_id' => $this->bizId, 'type' => 'purchase', 'status' => 'received', 'payment_status' => 'due',
        'transaction_date' => now(), 'final_total' => 300, 'total_before_tax' => 300, 'tax_amount' => 0,
        'created_by' => $this->user->id, 'essentials_duration' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('purchase_lines')->insert([
        'transaction_id' => $compraId, 'product_id' => $produto->productId, 'variation_id' => $variationId,
        'quantity' => 10, 'quantity_returned' => 0, 'pp_without_discount' => 30, 'purchase_price' => 30,
        'purchase_price_inc_tax' => 30, 'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
});

it('UC-VSHOW-08 · orçamento aberto traz custo guardado, custo de hoje e as duas margens', function () {
    ($this->entrar)($this->comCusto);

    $r = custoOrcGet($this, $this->saleId);
    $r->assertStatus(200);
    $page = json_decode($r->getContent(), true);

    expect($page['props'])->toHaveKey('detail');
    $custo = $page['props']['detail']['custo'];
    expect($custo)->not->toBeNull();

    $linha = collect($custo['linhas'])->firstWhere('id', $this->lineId);
    expect((float) $linha['previsto'])->toBe(20.0)
        ->and((float) $linha['hoje'])->toBe(30.0)
        ->and($linha['mudou'])->toBeTrue()
        ->and((float) $custo['total_previsto'])->toBe(40.0)
        ->and((float) $custo['total_hoje'])->toBe(60.0)
        ->and((float) $custo['margem_prevista_pct'])->toBe(60.0)
        ->and((float) $custo['margem_hoje_pct'])->toBe(40.0)
        ->and($custo['itens_mudaram'])->toBe(1)
        ->and($custo['data'])->toBe('2026-03-15');
});

it('UC-VSHOW-08 · sem view_purchase_price o custo nem chega ao navegador', function () {
    $r = custoOrcGet($this, $this->saleId);
    $r->assertStatus(200);
    $page = json_decode($r->getContent(), true);

    expect($page['props'])->toHaveKey('detail');
    expect($page['props']['detail']['custo'])->toBeNull();
    expect($r->getContent())->not->toContain('margem_prevista_pct');
});

it('UC-VSHOW-08 · venda finalizada não mostra o bloco de custo do orçamento', function () {
    ($this->entrar)($this->comCusto);
    DB::table('transactions')->where('id', $this->saleId)->update(['status' => 'final']);

    $page = json_decode(custoOrcGet($this, $this->saleId)->getContent(), true);

    expect($page['props'])->toHaveKey('detail');
    expect($page['props']['detail']['custo'])->toBeNull();
});
