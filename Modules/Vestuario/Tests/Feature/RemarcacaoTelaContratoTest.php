<?php
// @covers-us US-VEST-032

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Vestuario\Services\VestuarioSettingsResolver;
use Tests\Support\WithSeededTenant;

/**
 * US-VEST-032 — tela e endpoints da remarcação (UC-VREM-05..07).
 *
 * Âncora: decisão [W] 2026-10-27 (SPEC) — "feature nova nasce atrás de flag desligada"; remarcação
 * só para quem edita produto; etiquetas novas saem para impressão. Tenant 98 × 99 (ADR 0358).
 * NUNCA biz=4 (ROTA LIVRE).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('vestuario_remarcacoes')) {
        $this->markTestSkipped('Requer MySQL com o schema do UltimatePOS.');
    }
    try {
        $this->biz = $this->seededTenant();
        $this->outra = $this->seededSupportClientTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Tenant de teste ausente: ' . $e->getMessage());
    }
    $this->ligar = fn (int $biz, bool $on) => app(VestuarioSettingsResolver::class)->forBusiness($biz)->set('remarcacao.enabled', $on);
    $this->como = function (\App\User $u, int $biz) {
        test()->flushHeaders();

        return test()->actingAs($u)->withSession(['user.business_id' => $biz, 'user' => ['business_id' => $biz, 'id' => $u->id]]);
    };
    $this->editor = $this->usuarioComPermissoes(['product.update'], $this->biz);
    $this->tag = 'RemTela' . random_int(100000, 999999);
    $dono = (int) DB::table('business')->where('id', $this->biz->id)->value('owner_id');
    $unit = DB::table('units')->insertGetId(['business_id' => $this->biz->id, 'actual_name' => 'Peça', 'short_name' => 'pc', 'allow_decimal' => 0,
        'created_by' => $dono, 'created_at' => now(), 'updated_at' => now()]);
    $prod = DB::table('products')->insertGetId(['business_id' => $this->biz->id, 'name' => $this->tag . ' Vestido', 'type' => 'single',
        'unit_id' => $unit, 'tax_type' => 'inclusive', 'sku' => $this->tag, 'barcode_type' => 'C128', 'enable_stock' => 1,
        'alert_quantity' => 0, 'created_by' => $dono, 'created_at' => now(), 'updated_at' => now()]);
    $pv = DB::table('product_variations')->insertGetId(['product_id' => $prod, 'name' => 'DUMMY', 'is_dummy' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $this->var = DB::table('variations')->insertGetId(['name' => 'DUMMY', 'product_id' => $prod, 'product_variation_id' => $pv,
        'sub_sku' => $this->tag, 'default_purchase_price' => 50, 'dpp_inc_tax' => 50, 'profit_percent' => 100,
        'default_sell_price' => 100, 'sell_price_inc_tax' => 100, 'created_at' => now(), 'updated_at' => now()]);
});

it('UC-VREM-05 — sem o setting a tela e a prévia dão 404', function () {
    ($this->ligar)($this->biz->id, false);

    ($this->como)($this->editor, $this->biz->id)->get('/vestuario/remarcacao')->assertNotFound();
    ($this->como)($this->editor, $this->biz->id)
        ->postJson('/vestuario/remarcacao/previa', ['modo' => 'percentual', 'valor' => -30, 'filtro' => ['nome' => $this->tag]])
        ->assertNotFound();
});

it('UC-VREM-06 — ligada, abre só com product.update e só com dados da empresa [T0]', function () {
    ($this->ligar)($this->biz->id, true);
    $alheia = DB::table('brands')->insertGetId(['business_id' => $this->outra->id, 'name' => 'Marca alheia ' . $this->tag,
        'created_by' => (int) DB::table('business')->where('id', $this->outra->id)->value('owner_id'), 'created_at' => now(), 'updated_at' => now()]);

    ($this->como)($this->editor, $this->biz->id)->get('/vestuario/remarcacao')->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('Vestuario/Remarcacao/Index', false)
            ->where('marcas', fn ($m) => collect($m)->pluck('id')->doesntContain($alheia)));

    $semPermissao = $this->usuarioComPermissoes([], $this->biz);
    ($this->como)($semPermissao, $this->biz->id)->get('/vestuario/remarcacao')->assertForbidden();
});

it('UC-VREM-07 — prévia, gravar e etiquetas do lote; lote alheio não sai [T0]', function () {
    ($this->ligar)($this->biz->id, true);
    ($this->ligar)($this->outra->id, true);
    $corpo = ['modo' => 'percentual', 'valor' => -25, 'filtro' => ['nome' => $this->tag]];

    $previa = ($this->como)($this->editor, $this->biz->id)->postJson('/vestuario/remarcacao/previa', $corpo)->assertOk()->json();
    // à mão: 100,00 × 0,75 = 75,00
    expect($previa['linhas'][0]['preco_depois'])->toEqual(75.0);
    expect((float) DB::table('variations')->where('id', $this->var)->value('sell_price_inc_tax'))->toBe(100.0);

    $lote = ($this->como)($this->editor, $this->biz->id)
        ->postJson('/vestuario/remarcacao', $corpo + ['nome' => 'Liquidação teste', 'assinatura' => $previa['assinatura']])
        ->assertOk()->json('lote');
    expect((float) DB::table('variations')->where('id', $this->var)->value('sell_price_inc_tax'))->toBe(75.0);

    $itens = ($this->como)($this->editor, $this->biz->id)->getJson("/vestuario/remarcacao/{$lote}/etiquetas")->assertOk()->json('items');
    expect($itens)->toHaveCount(1);
    expect($itens[0]['preco'])->toEqual(75.0);

    $intruso = $this->usuarioComPermissoes(['product.update'], $this->outra);
    ($this->como)($intruso, $this->outra->id)->getJson("/vestuario/remarcacao/{$lote}/etiquetas")->assertNotFound();
    ($this->como)($intruso, $this->outra->id)->postJson("/vestuario/remarcacao/{$lote}/reverter")->assertStatus(422);
    expect((float) DB::table('variations')->where('id', $this->var)->value('sell_price_inc_tax'))->toBe(75.0);
});
