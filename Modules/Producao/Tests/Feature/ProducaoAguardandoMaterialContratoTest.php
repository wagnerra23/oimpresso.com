<?php
// @covers-us US-PCP-032

declare(strict_types=1);
// Cobre UC-PCMAT-01..05 (Producao/Apontamento/Index.casos.md) — D3 da proposta 2026-10-09 (revisão de 2026-10-21).

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Entities\Ordem;
use Modules\Producao\Entities\Posto;
use Modules\Producao\Entities\Programacao;
use Modules\Producao\Exceptions\ApontamentoRecusado;
use Modules\Producao\Services\ApontamentoOperadorService;
use Modules\Producao\Services\FaltaDeMaterialDaOs;
use Modules\Producao\Services\ProcessoDaOrdem;
use Modules\Producao\Services\SituacaoDaOrdem;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * US-PCP-032 — a OS liberada sem material fica em Aguardando material (D3, aprovada por [W] em
 * 2026-10-13; pedida para a Produção em 2026-10-21). Só LÊ estoque: cada número é feito à mão e o
 * saldo físico é conferido por SQL direto (não muda em nenhum caso).
 *
 * Arranjo, tenant 98 (NUNCA biz=4), local L:
 *   lona (insumo, controla estoque) · banner (vendido, não controla estoque) · receita: rende 2 un
 *   com 5 de lona → 2,5 lona/un · OS do banner, quantidade 2 → precisa de 5,0000 de lona.
 * Tenant 99 é o adversário. ⚠️ SKIP em SQLite / sem Fabricação: leia assertions (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS.');
    }
    if (! Schema::hasColumn('stock_reservations', 'pcp_ordem_id') || ! Schema::hasTable('mfg_recipes')) {
        $this->markTestSkipped('stock_reservations.pcp_ordem_id ou mfg_recipes ausente — migrations não rodaram.');
    }
    $this->partialMock(ModuleUtil::class, fn ($m) => $m->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($b, $perm) => $perm === 'producao_module'));
    $this->freezeTime();
    $this->B98 = $this->seededTenant();
    $this->b98 = (int) $this->B98->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->ligar = fn (bool $sim) => DB::table('business')->where('id', $this->b98)
        ->update(['common_settings' => json_encode($sim ? [FaltaDeMaterialDaOs::CHAVE => 1] : [])]);
    ($this->ligar)(true);
    app(ProcessoDaOrdem::class)->garantir($this->b98);
    $this->pcp = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $this->pcp->assignRole('producao.liberar#'.$this->b98);
    $this->op1 = $this->usuarioComPermissoes(['producao.access'], $this->B98);

    $esq = DB::table('invoice_schemes')->insertGetId(['business_id' => $this->b98, 'name' => 'Esq '.uniqid(), 'scheme_type' => 'blank']);
    $lay = DB::table('invoice_layouts')->insertGetId(['business_id' => $this->b98, 'name' => 'Lay '.uniqid()]);
    $this->localL = DB::table('business_locations')->insertGetId(['business_id' => $this->b98, 'name' => 'Loja '.uniqid(), 'country' => 'Brasil',
        'state' => 'SC', 'city' => 'Tubarão', 'zip_code' => '8870000', 'invoice_scheme_id' => $esq, 'invoice_layout_id' => $lay, 'is_active' => 1]);

    $produto = function (int $biz, string $nome, int $controla) {
        $unit = (int) (DB::table('units')->where('business_id', $biz)->value('id') ?? DB::table('units')->insertGetId([
            'business_id' => $biz, 'actual_name' => 'Unidade', 'short_name' => 'un', 'allow_decimal' => 1, 'created_by' => $this->op1->id,
            'created_at' => now(), 'updated_at' => now()]));
        $sku = 'MAT-'.uniqid();
        $pid = DB::table('products')->insertGetId(['business_id' => $biz, 'name' => $nome, 'type' => 'single', 'unit_id' => $unit,
            'sku' => $sku, 'enable_stock' => $controla, 'alert_quantity' => 0, 'tax_type' => 'exclusive', 'barcode_type' => 'C128',
            'created_by' => $this->op1->id, 'created_at' => now(), 'updated_at' => now()]);
        $grupo = DB::table('product_variations')->insertGetId(['product_id' => $pid, 'name' => 'DUMMY', 'is_dummy' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $vid = DB::table('variations')->insertGetId(['product_id' => $pid, 'product_variation_id' => $grupo, 'name' => 'DUMMY', 'sub_sku' => $sku.'-1',
            'default_purchase_price' => 10, 'dpp_inc_tax' => 10, 'profit_percent' => 0, 'default_sell_price' => 50, 'sell_price_inc_tax' => 50,
            'created_at' => now(), 'updated_at' => now()]);

        return [$pid, $vid, $grupo];
    };
    [$this->lona, $this->vLona, $grupo] = $produto($this->b98, 'Lona 440 g', 1);
    DB::table('variation_location_details')->insert(['product_id' => $this->lona, 'product_variation_id' => $grupo, 'variation_id' => $this->vLona,
        'location_id' => $this->localL, 'qty_available' => 3, 'created_at' => now(), 'updated_at' => now()]);
    [$this->banner, $this->vBanner] = $produto($this->b98, 'Banner', 0);
    $r = DB::table('mfg_recipes')->insertGetId(['product_id' => $this->banner, 'variation_id' => $this->vBanner, 'total_quantity' => 2,
        'final_price' => 0, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('mfg_recipe_ingredients')->insert(['mfg_recipe_id' => $r, 'variation_id' => $this->vLona, 'quantity' => 5, 'created_at' => now(), 'updated_at' => now()]);

    $this->venda = fn () => DB::table('transactions')->insertGetId(['business_id' => $this->b98, 'location_id' => $this->localL, 'type' => 'sell', 'status' => 'final',
        'payment_status' => 'due', 'transaction_date' => now(), 'final_total' => 500, 'essentials_duration' => 0, 'created_by' => $this->pcp->id, 'created_at' => now(), 'updated_at' => now()]);
    $this->compra = fn (string $status, string $qtd) => DB::table('purchase_lines')->insert(['transaction_id' => DB::table('transactions')->insertGetId([
        'business_id' => $this->b98, 'location_id' => $this->localL, 'type' => 'purchase', 'status' => $status, 'ref_no' => 'PC-'.$status,
        'payment_status' => 'due', 'transaction_date' => now(), 'final_total' => 0, 'essentials_duration' => 0, 'created_by' => $this->op1->id,
        'created_at' => now(), 'updated_at' => now()]), 'product_id' => $this->lona, 'variation_id' => $this->vLona, 'quantity' => $qtd, 'quantity_returned' => 0,
        'pp_without_discount' => 10, 'purchase_price' => 10, 'purchase_price_inc_tax' => 10, 'item_tax' => 0, 'created_at' => now(), 'updated_at' => now()]);
    $this->saldo = fn (string $q) => DB::table('variation_location_details')->where('variation_id', $this->vLona)->where('location_id', $this->localL)
        ->update(['qty_available' => $q]);
    // 2º caminho: saldo físico lido direto — este serviço nunca o muda.
    $this->saldoFisico = fn () => (string) DB::table('variation_location_details')->where('variation_id', $this->vLona)->where('location_id', $this->localL)->value('qty_available');

    // OS do banner (qtd 2) com uma etapa e responsável, pronta para Liberar.
    $this->novaOs = function (?int $venda = null) {
        // SUPERADMIN: fixture da empresa 98.
        $posto = Posto::withoutGlobalScopes()->create(['business_id' => $this->b98, 'codigo' => 'IMP-'.uniqid(), 'nome' => 'Impressão',
            'tipo_custo' => 'pessoa', 'custo_hora' => '30.00']);
        $ordem = Ordem::withoutGlobalScopes()->create(['business_id' => $this->b98, 'transaction_id' => $venda ?? ($this->venda)(),
            'transaction_sell_line_id' => random_int(1000000, 9999999), 'product_id' => $this->banner, 'variation_id' => $this->vBanner,
            'quantidade' => '2.0000', 'current_stage_id' => app(ProcessoDaOrdem::class)->etapaInicial($this->b98)->id]);
        $etapa = Programacao::withoutGlobalScopes()->create(['business_id' => $this->b98, 'os_tipo' => Ordem::OS_TIPO, 'os_id' => $ordem->id,
            'posto_id' => $posto->id, 'bloco' => 1, 'trilha' => 0, 'nome' => 'Impressão', 'user_id' => $this->op1->id,
            'inicio_previsto' => now()->subHours(2), 'fim_previsto' => now(), 'status' => 'pendente', 'created_by' => $this->pcp->id]);

        return [$ordem, $etapa];
    };
    $this->situacao = fn (Ordem $o) => $o->fresh('currentStage')->currentStage?->key;
    $this->iniciar = fn (Programacao $e) => app(ApontamentoOperadorService::class)->iniciar($this->b98, $this->op1->id, $e->id, 'i-'.uniqid(), now()->subHour());
});

it('UC-PCMAT-01 · chave da empresa desligada: liberar sem material segue para Na fila, como antes', function () {
    ($this->ligar)(false);
    [$ordem] = ($this->novaOs)();

    app(SituacaoDaOrdem::class)->liberar($this->b98, $ordem->id, $this->pcp);

    expect(($this->situacao)($ordem))->toBe('na_fila')->and(($this->saldoFisico)())->toBe('3.0000');
});

it('UC-PCMAT-02 · liberada sem material: fica Aguardando material, com o que falta e a compra a caminho; não começa', function () {
    ($this->compra)('ordered', '4');   // a caminho
    ($this->compra)('pending', '1');   // a caminho
    ($this->compra)('received', '9');  // já entrou no saldo: não é "a caminho"
    [$ordem, $etapa] = ($this->novaOs)();

    app(SituacaoDaOrdem::class)->liberar($this->b98, $ordem->id, $this->pcp);

    expect(($this->situacao)($ordem))->toBe('aguardando_material');
    // À mão: precisa 2,5 × 2 = 5,0000; disponível 3,0000; falta 2,0000; a caminho 4 + 1 = 5,0000.
    $r = $this->actingAs($this->op1)->getJson("/producao/ordens/{$ordem->id}/material")->assertOk()
        ->assertJson(['situacao' => 'aguardando_material']);
    expect($r->json('falta'))->toHaveCount(1)
        ->and($r->json('falta.0'))->toMatchArray(['variation_id' => $this->vLona, 'nome' => 'Lona 440 g', 'necessario' => '5.0000',
            'disponivel' => '3.0000', 'falta' => '2.0000', 'a_caminho' => '5.0000'])
        ->and(array_column($r->json('falta.0.compras'), 'ref_no'))->toBe(['PC-ordered', 'PC-pending']);

    expect(fn () => ($this->iniciar)($etapa))->toThrow(ApontamentoRecusado::class, 'aguardando material');
    expect(($this->situacao)($ordem))->toBe('aguardando_material')->and(($this->saldoFisico)())->toBe('3.0000');
});

it('UC-PCMAT-03 · a reserva de outra venda tira do disponível; a reserva da própria OS não', function () {
    ($this->saldo)('10');
    [$ordem] = ($this->novaOs)();
    $outra = ($this->venda)();
    $res = fn (int $tx, ?int $os, string $q) => DB::table('stock_reservations')->insert(['business_id' => $this->b98, 'transaction_id' => $tx,
        'pcp_ordem_id' => $os, 'product_id' => $this->lona, 'variation_id' => $this->vLona, 'location_id' => $this->localL,
        'qty_reserved' => $q, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    $res($ordem->transaction_id, $ordem->id, '5');  // da própria OS
    $res($outra, null, '6');                        // de outra venda
    $res($outra, null, '50');                       // liberada: não conta
    DB::table('stock_reservations')->where('qty_reserved', 50)->where('variation_id', $this->vLona)->update(['status' => 'released']);

    // À mão: disponível = 10 − 6 = 4,0000 (a reserva de 5 é desta OS); falta 5 − 4 = 1,0000.
    $falta = app(FaltaDeMaterialDaOs::class)->daOrdem($this->b98, $ordem);
    expect($falta)->toHaveCount(1)->and($falta[0]['disponivel'])->toBe('4.0000')->and($falta[0]['falta'])->toBe('1.0000');
    // 2º caminho, SQL direto: 10 − Σ ativas de outras = 10 − 6.
    $outras = (string) DB::table('stock_reservations')->where('business_id', $this->b98)->where('variation_id', $this->vLona)
        ->where('status', 'active')->whereNull('pcp_ordem_id')->sum('qty_reserved');
    expect((float) $outras)->toBe(6.0);
});

it('UC-PCMAT-04 · conferir: com falta fica onde está; com o material no saldo volta para Na fila e começa', function () {
    [$ordem, $etapa] = ($this->novaOs)();
    app(SituacaoDaOrdem::class)->liberar($this->b98, $ordem->id, $this->pcp);
    expect(($this->situacao)($ordem))->toBe('aguardando_material');

    // Quem não tem o papel do PCP não confere.
    $this->actingAs($this->op1)->postJson("/producao/ordens/{$ordem->id}/material/conferir")->assertForbidden();

    $this->actingAs($this->pcp)->postJson("/producao/ordens/{$ordem->id}/material/conferir")->assertOk()
        ->assertJson(['situacao' => 'aguardando_material'])->assertJsonPath('falta.0.falta', '2.0000');

    ($this->saldo)('5');   // a compra chegou: 5 = exatamente o necessário
    $this->postJson("/producao/ordens/{$ordem->id}/material/conferir")->assertOk()->assertJson(['situacao' => 'na_fila', 'falta' => []]);
    expect(($this->situacao)($ordem))->toBe('na_fila');

    ($this->iniciar)($etapa);
    expect(($this->situacao)($ordem))->toBe('em_execucao')->and(($this->saldoFisico)())->toBe('5.0000');
});

it('UC-PCMAT-05 · OS de outra empresa → 404; nada muda', function () {
    $os99 = Ordem::withoutGlobalScopes()->create(['business_id' => $this->b99, 'transaction_id' => ($this->venda)(),
        'transaction_sell_line_id' => random_int(1000000, 9999999), 'product_id' => $this->banner, 'variation_id' => $this->vBanner,
        'quantidade' => '1.0000', 'current_stage_id' => app(ProcessoDaOrdem::class)->etapaInicial($this->b99)->id]);

    $this->actingAs($this->pcp)->getJson("/producao/ordens/{$os99->id}/material")->assertNotFound();
    $this->actingAs($this->pcp)->postJson("/producao/ordens/{$os99->id}/material/conferir")->assertNotFound();
    expect($os99->fresh('currentStage')->currentStage?->key)->toBe('aguardando_liberacao');
});
