<?php
// @covers-us US-PCP-022

declare(strict_types=1);
// Cobre UC-PCOSA-01..05 (Producao/Apontamento/Index.casos.md) — UC-FLX-36 do catálogo de fluxos.

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Entities\Ordem;
use Modules\Producao\Entities\Posto;
use Modules\Producao\Entities\Programacao;
use Modules\Producao\Services\ApontamentoOperadorService;
use Modules\Producao\Services\ConsumoMaterialService;
use Modules\Producao\Services\PrevisaoCustoDaOrdem;
use Modules\Producao\Services\ProcessoDaOrdem;
use Modules\Producao\Services\SituacaoDaOrdem;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * US-PCP-022 · UC-FLX-36 — a regra da compra (`ops`: congela | vivo) vale para a OS aberta.
 * Só leitura de VALOR; os números são feitos à mão sobre o arranjo e o "nada foi escrito" é medido.
 *
 * Arranjo, tenant 98, custo médio (padrão):
 *   compra A 01/03 · 10 un × 10,00 no local da venda       → médio em 12/03 = 10,0000
 *   OS aberta em 12/03; a etapa baixa 3 un (lote A)         → lote = 3 × 10,00 = 30,0000
 *   compra B 15/03 · 10 un × 20,00                          → médio em 20/03 = (100 + 200) / 20 = 15,0000
 *   hoje = 20/03 → abertura = 3 × 10,0000 = 30,0000 · hoje = 3 × 15,0000 = 45,0000
 *   congela: previsto = 30,0000 · vivo: previsto = 45,0000 · congelado: nada muda nos dois
 *
 * ⚠️ SKIP em SQLite: leia assertions (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS.');
    }
    if (! Schema::hasTable('pcp_consumos') || ! Schema::hasColumn('fsm_modelos_empresa', 'regras_compra')) {
        $this->markTestSkipped('pcp_consumos ou fsm_modelos_empresa.regras_compra ausente.');
    }
    Carbon::setTestNow('2026-03-12 10:00:00');
    $this->B98 = $this->seededTenant();
    $this->b98 = (int) $this->B98->id;
    DB::table('business')->where('id', $this->b98)->update(['accounting_method' => 'fifo', 'pos_settings' => json_encode(['allow_overselling' => 0])]);
    DB::table('fsm_modelos_empresa')->where('business_id', $this->b98)->delete();
    app(ProcessoDaOrdem::class)->garantir($this->b98);
    $this->pcp = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $this->pcp->assignRole('producao.liberar#'.$this->b98);
    $this->op = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $this->gestor = $this->usuarioComPermissoes(['producao.access', 'producao.desempenho.ver'], $this->B98);

    $esq = DB::table('invoice_schemes')->insertGetId(['business_id' => $this->b98, 'name' => 'Esq '.uniqid(), 'scheme_type' => 'blank']);
    $lay = DB::table('invoice_layouts')->insertGetId(['business_id' => $this->b98, 'name' => 'Lay '.uniqid()]);
    $this->local = DB::table('business_locations')->insertGetId(['business_id' => $this->b98, 'name' => 'Loja '.uniqid(), 'country' => 'Brasil',
        'state' => 'SC', 'city' => 'Tubarão', 'zip_code' => '8870000', 'invoice_scheme_id' => $esq, 'invoice_layout_id' => $lay, 'is_active' => 1]);
    $unit = (int) (DB::table('units')->where('business_id', $this->b98)->value('id') ?? DB::table('units')->insertGetId([
        'business_id' => $this->b98, 'actual_name' => 'Unidade', 'short_name' => 'un', 'allow_decimal' => 1, 'created_by' => $this->op->id,
        'created_at' => now(), 'updated_at' => now()]));
    $sku = 'OSA-'.uniqid();
    $this->pid = DB::table('products')->insertGetId(['business_id' => $this->b98, 'name' => 'Vinil '.$sku, 'type' => 'single', 'unit_id' => $unit,
        'sku' => $sku, 'enable_stock' => 1, 'alert_quantity' => 0, 'tax_type' => 'exclusive', 'barcode_type' => 'C128',
        'created_by' => $this->op->id, 'created_at' => now(), 'updated_at' => now()]);
    $grupo = DB::table('product_variations')->insertGetId(['product_id' => $this->pid, 'name' => 'DUMMY', 'is_dummy' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $this->vid = DB::table('variations')->insertGetId(['product_id' => $this->pid, 'product_variation_id' => $grupo, 'name' => 'DUMMY', 'sub_sku' => $sku.'-1',
        'default_purchase_price' => 99, 'dpp_inc_tax' => 99, 'profit_percent' => 0, 'default_sell_price' => 150, 'sell_price_inc_tax' => 150,
        'created_at' => now(), 'updated_at' => now()]);
    DB::table('variation_location_details')->insert(['product_id' => $this->pid, 'product_variation_id' => $grupo, 'variation_id' => $this->vid,
        'location_id' => $this->local, 'qty_available' => 20, 'created_at' => now(), 'updated_at' => now()]);
    $this->compra = function (string $data, string $qtd, string $preco): void {
        $tid = DB::table('transactions')->insertGetId(['business_id' => $this->b98, 'location_id' => $this->local, 'type' => 'purchase', 'status' => 'received',
            'payment_status' => 'due', 'transaction_date' => $data, 'final_total' => 0, 'essentials_duration' => 0, 'created_by' => $this->op->id,
            'created_at' => now(), 'updated_at' => now()]);
        DB::table('purchase_lines')->insert(['transaction_id' => $tid, 'product_id' => $this->pid, 'variation_id' => $this->vid,
            'quantity' => $qtd, 'quantity_returned' => 0, 'pp_without_discount' => $preco, 'purchase_price' => $preco,
            'purchase_price_inc_tax' => $preco, 'item_tax' => 0, 'created_at' => now(), 'updated_at' => now()]);
    };
    ($this->compra)('2026-03-01 09:00:00', '10', '10.00');

    $venda = DB::table('transactions')->insertGetId(['business_id' => $this->b98, 'location_id' => $this->local, 'type' => 'sell', 'status' => 'final',
        'payment_status' => 'due', 'transaction_date' => now(), 'final_total' => 500, 'essentials_duration' => 0, 'created_by' => $this->pcp->id,
        'created_at' => now(), 'updated_at' => now()]);
    // SUPERADMIN: fixture da empresa 98.
    $posto = Posto::withoutGlobalScopes()->create(['business_id' => $this->b98, 'codigo' => 'OSA-'.uniqid(), 'nome' => 'Impressão',
        'tipo_custo' => 'pessoa', 'custo_hora' => '30.00']);
    $this->ordem = Ordem::withoutGlobalScopes()->create(['business_id' => $this->b98, 'transaction_id' => $venda,
        'transaction_sell_line_id' => random_int(1000000, 9999999), 'product_id' => $this->pid, 'quantidade' => '1.0000',
        'current_stage_id' => app(ProcessoDaOrdem::class)->etapaInicial($this->b98)->id]);
    $this->etapa = Programacao::withoutGlobalScopes()->create(['business_id' => $this->b98, 'os_tipo' => Ordem::OS_TIPO, 'os_id' => $this->ordem->id,
        'posto_id' => $posto->id, 'bloco' => 1, 'trilha' => 0, 'nome' => 'Impressão', 'user_id' => $this->op->id,
        'inicio_previsto' => now()->subHour(), 'fim_previsto' => now(), 'status' => 'pendente', 'created_by' => $this->pcp->id]);
    app(SituacaoDaOrdem::class)->liberar($this->b98, $this->ordem->id, $this->pcp);
    $this->inicio = app(ApontamentoOperadorService::class)->iniciar($this->b98, $this->op->id, $this->etapa->id, 'i-'.uniqid(), now()->subMinutes(30));
    $this->consumo = app(ConsumoMaterialService::class)->registrar($this->b98, $this->op->id, $this->etapa->id, $this->vid, '3', 'm-'.uniqid(), now());

    ($this->compra)('2026-03-15 09:00:00', '10', '20.00');
    Carbon::setTestNow('2026-03-20 10:00:00');

    $this->regra = fn (string $ops, bool $ativo = true) => DB::table('fsm_modelos_empresa')->updateOrInsert(['business_id' => $this->b98],
        ['modelo' => 'cv-pequena', 'regras_compra' => json_encode(['insumo.custo' => ['ativo' => $ativo, 'params' => ['ops' => $ops]]]),
            'created_at' => now(), 'updated_at' => now()]);
    $this->escrito = fn () => [(string) $this->ordem->fresh()->custo_real, (string) $this->consumo->fresh()->custo,
        DB::table('pcp_consumos')->where('ordem_id', $this->ordem->id)->count()];
});

afterEach(fn () => Carbon::setTestNow());

it('UC-PCOSA-01 · congela: a OS aberta mostra o custo da abertura e o de hoje, e a previsão fica na abertura', function () {
    ($this->regra)('congela');
    $antes = ($this->escrito)();

    $p = app(PrevisaoCustoDaOrdem::class)->daOrdem($this->b98, $this->ordem->id);

    expect($p['regra'])->toBe('congela')->and($p['aberta_em'])->toBe('2026-03-12');
    expect($p['itens'][0])->toMatchArray(['variation_id' => $this->vid, 'quantidade' => '3.0000', 'custo_unit_abertura' => '10.0000',
        'custo_unit_hoje' => '15.0000', 'abertura' => '30.0000', 'hoje' => '45.0000', 'lote' => '30.0000', 'mudou' => true]);
    expect($p['total_abertura'])->toBe('30.0000')->and($p['total_hoje'])->toBe('45.0000')
        ->and($p['previsto'])->toBe('30.0000')->and($p['itens_mudaram'])->toBe(1)->and($p['incompleto'])->toBeFalse();
    expect(($this->escrito)())->toBe($antes);                                  // só leitura
});

it('UC-PCOSA-02 · vivo: a previsão acompanha o custo de hoje, sem escrever nada', function () {
    ($this->regra)('vivo');
    $antes = ($this->escrito)();

    $p = app(PrevisaoCustoDaOrdem::class)->daOrdem($this->b98, $this->ordem->id);

    expect($p['regra'])->toBe('vivo')->and($p['previsto'])->toBe('45.0000')->and($p['total_lote'])->toBe('30.0000');
    expect(($this->escrito)())->toBe($antes);
    // Regra desligada não propaga nada: vale como congela.
    ($this->regra)('vivo', false);
    expect(app(PrevisaoCustoDaOrdem::class)->daOrdem($this->b98, $this->ordem->id)['previsto'])->toBe('30.0000');
});

it('UC-PCOSA-03 · sem regra gravada vale o padrão congela', function () {
    expect(app(PrevisaoCustoDaOrdem::class)->regraOps($this->b98))->toBe('congela');
    expect(app(PrevisaoCustoDaOrdem::class)->daOrdem($this->b98, $this->ordem->id)['previsto'])->toBe('30.0000');
});

it('UC-PCOSA-04 · OS concluída mostra só o congelado (horas + lote), nas duas regras', function () {
    ($this->regra)('vivo');
    // Conclui às 10:30 de 12/03 (o apontamento não aceita hora com mais de 72 h de atraso).
    Carbon::setTestNow('2026-03-12 10:30:00');
    app(ApontamentoOperadorService::class)->concluir($this->b98, $this->op->id, $this->inicio->id, 'c-'.uniqid(), now());
    Carbon::setTestNow('2026-03-20 10:00:00');
    // 1 h × 30,00 (09:30→10:30) + material 30,00 = 60,00.
    expect((string) $this->ordem->fresh()->custo_real)->toBe('60.0000');

    $p = app(PrevisaoCustoDaOrdem::class)->daOrdem($this->b98, $this->ordem->id);
    expect($p['situacao'])->toBe('concluida')->and($p['congelado'])->toBe('60.0000')
        ->and($p['previsto'])->toBeNull()->and($p['itens'])->toBe([]);
});

it('UC-PCOSA-05 · o custo da OS é do gestor: sem permissão 403, OS de outra empresa 404', function () {
    $this->partialMock(ModuleUtil::class, fn ($m) => $m->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($b, $perm) => $perm === 'producao_module'));
    ($this->regra)('congela');

    $this->actingAs($this->gestor)->getJson("/producao/ordens/{$this->ordem->id}/custo")->assertOk()
        ->assertJson(['regra' => 'congela', 'previsto' => '30.0000', 'total_hoje' => '45.0000']);
    $this->actingAs($this->op)->getJson("/producao/ordens/{$this->ordem->id}/custo")->assertForbidden();

    $outra = (int) DB::table('business')->where('id', '!=', $this->b98)->value('id');
    // SUPERADMIN: OS da outra empresa, para provar o 404.
    $alheia = Ordem::withoutGlobalScopes()->create(['business_id' => $outra, 'transaction_id' => 990077,
        'transaction_sell_line_id' => random_int(1000000, 9999999), 'product_id' => 1, 'quantidade' => '1.0000']);
    $this->actingAs($this->gestor)->getJson("/producao/ordens/{$alheia->id}/custo")->assertNotFound();
});
