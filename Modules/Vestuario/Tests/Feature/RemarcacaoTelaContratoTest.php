<?php
// @covers-us US-VEST-032

declare(strict_types=1);

use App\Facades\Menu;
use App\Services\LegacyMenuAdapter;
use App\Utils\ModuleUtil;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Vestuario\Http\Controllers\DataController;
use Modules\Vestuario\Http\Controllers\RemarcacaoController;
use Modules\Vestuario\Services\Remarcacao;
use Modules\Vestuario\Services\VestuarioSettingsResolver;
use Tests\Support\WithSeededTenant;

/**
 * US-VEST-032 — tela e endpoints da remarcação (UC-VREM-05..07) e limite de geração (UC-VET-10).
 *
 * Âncora: decisão [W] 2026-10-27 (SPEC) — "feature nova nasce atrás de flag desligada"; etiquetas
 * novas saem para impressão. Decisão [W] 2026-11-09: remarcação tem permissão própria, não a de
 * editar produto. Tenant 98 × 99 (ADR 0358).
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
    $this->editor = $this->usuarioComPermissoes([RemarcacaoController::PERMISSAO], $this->biz);
    // Caminho real do menu (DataController → LegacyMenuAdapter). O pacote é mock: no CI o ModuleUtil real libera tudo.
    $this->ghostsDoMenu = function (\App\User $u, int $biz): array {
        $util = Mockery::mock(ModuleUtil::class)->makePartial();
        $util->shouldReceive('hasThePermissionInSubscription')->andReturn(true);
        app()->instance(ModuleUtil::class, $util);
        test()->actingAs($u);
        session(['user' => ['business_id' => $biz, 'id' => $u->id]]);
        Menu::create('admin-sidebar-menu', fn ($menu) => $menu);
        (new DataController())->modifyAdminMenu();
        $item = collect((new LegacyMenuAdapter())->build())->firstWhere('label', 'Vestuário');

        return collect($item['ghosts'] ?? [])->pluck('href', 'key')->all();
    };
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

it('UC-VREM-01 — a resposta da prévia traz a contagem de preço de grupo', function () {
    ($this->ligar)($this->biz->id, true);
    $grupo = DB::table('selling_price_groups')->insertGetId(['business_id' => $this->biz->id, 'name' => 'Atacado ' . $this->tag,
        'created_at' => now(), 'updated_at' => now()]);
    DB::table('variation_group_prices')->insert(['variation_id' => $this->var, 'price_group_id' => $grupo, 'price_inc_tax' => 50.00,
        'price_type' => 'fixed', 'created_at' => now(), 'updated_at' => now()]);

    $r = ($this->como)($this->editor, $this->biz->id)
        ->postJson('/vestuario/remarcacao/previa', ['modo' => 'percentual', 'valor' => -30, 'filtro' => ['nome' => $this->tag]])->assertOk();

    expect($r->json('grupos'))->toBe(['fixos' => 1, 'percentuais' => 0]);
});

it('UC-VREM-06 — ligada, abre só com a permissão própria e só com dados da empresa [T0]', function () {
    ($this->ligar)($this->biz->id, true);
    $alheia = DB::table('brands')->insertGetId(['business_id' => $this->outra->id, 'name' => 'Marca alheia ' . $this->tag,
        'created_by' => (int) DB::table('business')->where('id', $this->outra->id)->value('owner_id'), 'created_at' => now(), 'updated_at' => now()]);

    ($this->como)($this->editor, $this->biz->id)->get('/vestuario/remarcacao')->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('Vestuario/Remarcacao/Index', false)
            ->where('marcas', fn ($m) => collect($m)->pluck('id')->doesntContain($alheia)));

    $semPermissao = $this->usuarioComPermissoes([], $this->biz);
    ($this->como)($semPermissao, $this->biz->id)->get('/vestuario/remarcacao')->assertForbidden();
});

it('UC-VREM-06 — quem só edita produto não remarca, nem na prévia nem revertendo [T0]', function () {
    ($this->ligar)($this->biz->id, true);
    $soProduto = $this->usuarioComPermissoes(['product.update'], $this->biz);

    ($this->como)($soProduto, $this->biz->id)->get('/vestuario/remarcacao')->assertForbidden();
    ($this->como)($soProduto, $this->biz->id)
        ->postJson('/vestuario/remarcacao/previa', ['modo' => 'percentual', 'valor' => -30, 'filtro' => ['nome' => $this->tag]])
        ->assertForbidden();
    ($this->como)($soProduto, $this->biz->id)->postJson('/vestuario/remarcacao/999999999/reverter')->assertForbidden();
});

it('UC-VREM-06 — o menu mostra Remarcação só com a permissão e a remarcação ligada', function () {
    $comPermissao = $this->usuarioComPermissoes(['vestuario.access', RemarcacaoController::PERMISSAO], $this->biz);
    $semPermissao = $this->usuarioComPermissoes(['vestuario.access'], $this->biz);

    ($this->ligar)($this->biz->id, true);
    expect(($this->ghostsDoMenu)($comPermissao, $this->biz->id))->toBe([
        'etiquetas' => '/vestuario/etiquetas',
        'remarcacao' => '/vestuario/remarcacao',
    ]);
    expect(($this->ghostsDoMenu)($semPermissao, $this->biz->id))->toBe(['etiquetas' => '/vestuario/etiquetas']);

    ($this->ligar)($this->biz->id, false);
    expect(($this->ghostsDoMenu)($comPermissao, $this->biz->id))->toBe(['etiquetas' => '/vestuario/etiquetas']);
});

it('UC-VREM-02 — erro de banco vira mensagem genérica, sem SQL na tela', function () {
    ($this->ligar)($this->biz->id, true);
    $svc = Mockery::mock(Remarcacao::class);
    $svc->shouldReceive('previa')->andThrow(new QueryException('mysql', 'select segredo from tabela', [], new RuntimeException('SQLSTATE[40001] deadlock')));
    app()->instance(Remarcacao::class, $svc);

    $r = ($this->como)($this->editor, $this->biz->id)
        ->postJson('/vestuario/remarcacao/previa', ['modo' => 'percentual', 'valor' => -30, 'filtro' => ['nome' => $this->tag]])
        ->assertStatus(500);

    expect($r->json('message'))->toBe('Não foi possível concluir agora. Tente de novo.');
    expect($r->getContent())->not->toContain('SQLSTATE');
    expect($r->getContent())->not->toContain('segredo');
});

it('UC-VET-10 — gerar etiqueta tem limite por usuário: a 11ª no minuto dá 429 e outro usuário segue', function () {
    $operador = $this->usuarioComPermissoes(['vestuario.etiqueta.create'], $this->biz);
    $outro = $this->usuarioComPermissoes(['vestuario.etiqueta.create'], $this->biz);
    $loteVazio = ['items' => []]; // recusado na validação: conta no limite sem gerar arquivo

    foreach (range(1, 10) as $tentativa) {
        ($this->como)($operador, $this->biz->id)->postJson('/vestuario/etiquetas/lote/zpl', $loteVazio)->assertStatus(422);
    }

    // O limite é do fluxo, não do formato: a 11ª vai para o PDF e também para.
    ($this->como)($operador, $this->biz->id)->postJson('/vestuario/etiquetas/lote/pdf', $loteVazio)
        ->assertStatus(429)->assertSee('Aguarde um minuto');
    ($this->como)($outro, $this->biz->id)->postJson('/vestuario/etiquetas/lote/pdf', $loteVazio)->assertStatus(422);
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

    $intruso = $this->usuarioComPermissoes([RemarcacaoController::PERMISSAO], $this->outra);
    ($this->como)($intruso, $this->outra->id)->getJson("/vestuario/remarcacao/{$lote}/etiquetas")->assertNotFound();
    ($this->como)($intruso, $this->outra->id)->postJson("/vestuario/remarcacao/{$lote}/reverter")->assertStatus(422);
    expect((float) DB::table('variations')->where('id', $this->var)->value('sell_price_inc_tax'))->toBe(75.0);
});

describe('UC-VREM-07 — etiquetas de um lote gravado', function () {
    beforeEach(function () {
        ($this->ligar)($this->biz->id, true);
        $corpo = ['modo' => 'percentual', 'valor' => -25, 'filtro' => ['nome' => $this->tag]];
        $previa = ($this->como)($this->editor, $this->biz->id)->postJson('/vestuario/remarcacao/previa', $corpo)->assertOk()->json();
        $this->lote = ($this->como)($this->editor, $this->biz->id)
            ->postJson('/vestuario/remarcacao', $corpo + ['nome' => 'Etiquetas teste', 'assinatura' => $previa['assinatura']])
            ->assertOk()->json('lote');
    });

    it('UC-VREM-07 — lote revertido não imprime etiqueta com o preço que saiu da loja', function () {
        ($this->como)($this->editor, $this->biz->id)->postJson("/vestuario/remarcacao/{$this->lote}/reverter")->assertOk();

        $r = ($this->como)($this->editor, $this->biz->id)->getJson("/vestuario/remarcacao/{$this->lote}/etiquetas")->assertStatus(422);
        expect($r->json('message'))->toContain('revertida');
    });

    it('UC-VREM-07 — peça mexida depois sai com o preço de hoje, não o da remarcação', function () {
        DB::table('variations')->where('id', $this->var)->update(['sell_price_inc_tax' => 80.00]);

        $itens = ($this->como)($this->editor, $this->biz->id)->getJson("/vestuario/remarcacao/{$this->lote}/etiquetas")->assertOk()->json('items');
        expect($itens[0]['preco'])->toEqual(80.0);
    });

    it('UC-VREM-07 — produto simples não leva a variação interna como tamanho', function () {
        $itens = ($this->como)($this->editor, $this->biz->id)->getJson("/vestuario/remarcacao/{$this->lote}/etiquetas")->assertOk()->json('items');
        expect($itens[0]['tamanho'])->toBeNull();
    });
});
