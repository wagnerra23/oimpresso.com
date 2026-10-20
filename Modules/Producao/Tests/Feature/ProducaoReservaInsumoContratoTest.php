<?php
// @covers-us US-PCP-031

declare(strict_types=1);
// Cobre UC-PCRES-01..07 (Producao/Apontamento/Index.casos.md) — UC-FLX-14 do catálogo de fluxos.

use App\Contracts\Fabricacao\InsumosDaReceita;
use App\Domain\Fsm\Modelos\AplicarModelo;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\StockReservation;
use App\Domain\Fsm\Support\FsmAuthorizationFlag;
use App\Scopes\ScopeByBusiness;
use App\Transaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Entities\Ordem;
use Modules\Producao\Entities\Posto;
use Modules\Producao\Entities\Programacao;
use Modules\Producao\Services\ApontamentoOperadorService;
use Modules\Producao\Services\ConsumoMaterialService;
use Modules\Producao\Services\CriarOsDaVenda;
use Modules\Producao\Services\ProcessoDaOrdem;
use Modules\Producao\Services\ReservaDeInsumoDaOs;
use Modules\Producao\Services\SituacaoDaOrdem;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * US-PCP-031 · UC-FLX-14 — decisão [W] 2026-10-20: "Reserva insumo na venda, baixa real na produção".
 * ESTOQUE (regra mestre): cada número é feito à mão e conferido por um 2º caminho (SQL direto em
 * `stock_reservations` e `variation_location_details`, sem o serviço). Saldo físico e reservado em
 * cada passo.
 *
 * Arranjo, tenant 98 (FIFO, sem vender sem estoque), local L:
 *   lona (insumo, controla estoque) · saldo em L = 10 · lotes em L: A 2 un + B 5 un = 7
 *   banner (produto vendido, NÃO controla estoque) · receita: rende 2 un com 5 de lona → 2,5 lona/un
 *   placa pronta (revenda, controla estoque) · tem a mesma receita — não pode reservar
 *   OS do banner, quantidade 2 → reserva = 2,5 × 2 = 5,0000 de lona
 * Tenant 99 é o adversário. ⚠️ SKIP em SQLite: leia assertions (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS.');
    }
    if (! Schema::hasColumn('stock_reservations', 'pcp_ordem_id') || ! Schema::hasTable('mfg_recipes')) {
        $this->markTestSkipped('stock_reservations.pcp_ordem_id ou mfg_recipes ausente — migrations não rodaram.');
    }
    $this->freezeTime();
    $this->B98 = $this->seededTenant();
    $this->b98 = (int) $this->B98->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    DB::table('business')->where('id', $this->b98)->update(['accounting_method' => 'fifo', 'pos_settings' => json_encode(['allow_overselling' => 0]),
        'common_settings' => json_encode([ReservaDeInsumoDaOs::CHAVE => 1])]);
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
        $sku = 'RES-'.uniqid();
        $pid = DB::table('products')->insertGetId(['business_id' => $biz, 'name' => $nome.' '.$sku, 'type' => 'single', 'unit_id' => $unit,
            'sku' => $sku, 'enable_stock' => $controla, 'alert_quantity' => 0, 'tax_type' => 'exclusive', 'barcode_type' => 'C128',
            'created_by' => $this->op1->id, 'created_at' => now(), 'updated_at' => now()]);
        $grupo = DB::table('product_variations')->insertGetId(['product_id' => $pid, 'name' => 'DUMMY', 'is_dummy' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $vid = DB::table('variations')->insertGetId(['product_id' => $pid, 'product_variation_id' => $grupo, 'name' => 'DUMMY', 'sub_sku' => $sku.'-1',
            'default_purchase_price' => 10, 'dpp_inc_tax' => 10, 'profit_percent' => 0, 'default_sell_price' => 50, 'sell_price_inc_tax' => 50,
            'created_at' => now(), 'updated_at' => now()]);

        return [$pid, $vid, $grupo];
    };
    [$this->lona, $this->vLona, $grupo] = $produto($this->b98, 'Lona', 1);
    DB::table('variation_location_details')->insert(['product_id' => $this->lona, 'product_variation_id' => $grupo, 'variation_id' => $this->vLona,
        'location_id' => $this->localL, 'qty_available' => 10, 'created_at' => now(), 'updated_at' => now()]);
    foreach ([['2026-03-01 09:00:00', '2', '10.00'], ['2026-03-10 09:00:00', '5', '14.00']] as [$data, $qtd, $preco]) {
        $tid = DB::table('transactions')->insertGetId(['business_id' => $this->b98, 'location_id' => $this->localL, 'type' => 'purchase', 'status' => 'received',
            'payment_status' => 'due', 'transaction_date' => $data, 'final_total' => 0, 'essentials_duration' => 0, 'created_by' => $this->op1->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('purchase_lines')->insert(['transaction_id' => $tid, 'product_id' => $this->lona, 'variation_id' => $this->vLona,
            'quantity' => $qtd, 'quantity_returned' => 0, 'pp_without_discount' => $preco, 'purchase_price' => $preco,
            'purchase_price_inc_tax' => $preco, 'item_tax' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    $receita = function (int $pid, int $vid): void {
        $r = DB::table('mfg_recipes')->insertGetId(['product_id' => $pid, 'variation_id' => $vid, 'ingredients' => '', 'total_quantity' => 2,
            'final_price' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('mfg_recipe_ingredients')->insert(['mfg_recipe_id' => $r, 'variation_id' => $this->vLona, 'quantity' => 5, 'created_at' => now(), 'updated_at' => now()]);
    };
    [$this->banner, $this->vBanner] = $produto($this->b98, 'Banner', 0);
    $receita($this->banner, $this->vBanner);
    [$this->placa, $this->vPlaca] = $produto($this->b98, 'Placa pronta', 1);
    $receita($this->placa, $this->vPlaca);

    $this->venda = DB::table('transactions')->insertGetId(['business_id' => $this->b98, 'location_id' => $this->localL, 'type' => 'sell', 'status' => 'final',
        'payment_status' => 'due', 'transaction_date' => now(), 'final_total' => 500, 'essentials_duration' => 0, 'created_by' => $this->pcp->id, 'created_at' => now(), 'updated_at' => now()]);

    $this->svc = app(ReservaDeInsumoDaOs::class);
    $this->saldo = fn () => (string) DB::table('variation_location_details')->where('variation_id', $this->vLona)->where('location_id', $this->localL)->value('qty_available');
    // 2º caminho: SQL direto, sem o serviço. Reservado = soma das reservas ativas da lona na venda.
    $this->reservado = fn () => (string) (DB::table('stock_reservations')->where('business_id', $this->b98)->where('variation_id', $this->vLona)
        ->where('transaction_id', $this->venda)->where('status', 'active')->selectRaw('COALESCE(SUM(qty_reserved), 0) as q')->value('q'));
    $this->reserva = fn () => DB::table('stock_reservations')->where('business_id', $this->b98)->where('transaction_id', $this->venda)->get();

    // OS do banner pronta para apontar (mesmo caminho do ProducaoMaterialContratoTest).
    $this->novaOs = function () {
        // SUPERADMIN: fixture da empresa 98.
        $posto = Posto::withoutGlobalScopes()->create(['business_id' => $this->b98, 'codigo' => 'IMP-'.uniqid(), 'nome' => 'Impressão',
            'tipo_custo' => 'pessoa', 'custo_hora' => '30.00']);
        $ordem = Ordem::withoutGlobalScopes()->create(['business_id' => $this->b98, 'transaction_id' => $this->venda,
            'transaction_sell_line_id' => random_int(1000000, 9999999), 'product_id' => $this->banner, 'variation_id' => $this->vBanner,
            'quantidade' => '2.0000', 'current_stage_id' => app(ProcessoDaOrdem::class)->etapaInicial($this->b98)->id]);
        $etapa = Programacao::withoutGlobalScopes()->create(['business_id' => $this->b98, 'os_tipo' => Ordem::OS_TIPO, 'os_id' => $ordem->id,
            'posto_id' => $posto->id, 'bloco' => 1, 'trilha' => 0, 'nome' => 'Impressão', 'user_id' => $this->op1->id,
            'inicio_previsto' => now()->subHours(2), 'fim_previsto' => now(), 'status' => 'pendente', 'created_by' => $this->pcp->id]);
        app(SituacaoDaOrdem::class)->liberar($this->b98, $ordem->id, $this->pcp);
        $this->svc->reservar($this->b98, $ordem);
        $inicio = app(ApontamentoOperadorService::class)->iniciar($this->b98, $this->op1->id, $etapa->id, 'i-'.uniqid(), now()->subHour());

        return [$ordem, $etapa, $inicio];
    };
    $this->baixar = fn (Programacao $etapa, string $qtd) => app(ConsumoMaterialService::class)
        ->registrar($this->b98, $this->op1->id, $etapa->id, $this->vLona, $qtd, 'm-'.uniqid(), now());

    // Venda aprovada: uma linha + roteiro de uma etapa, como o CriarOsDaVenda lê.
    $this->linhaComRoteiro = function (int $pid, int $vid, int $qtd): void {
        DB::table('transaction_sell_lines')->insert(['transaction_id' => $this->venda, 'product_id' => $pid, 'variation_id' => $vid, 'quantity' => $qtd,
            'unit_price' => 50, 'unit_price_inc_tax' => 50, 'created_at' => now(), 'updated_at' => now()]);
        $rot = DB::table('pcp_roteiros')->insertGetId(['business_id' => $this->b98, 'familia' => 'Impressão', 'nome' => 'Banner '.uniqid(), 'ativo' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('pcp_roteiro_etapas')->insert(['business_id' => $this->b98, 'roteiro_id' => $rot, 'bloco' => 1, 'trilha' => 0, 'ordem' => 0,
            'nome' => 'Impressão', 'horas' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('pcp_produto_roteiro')->insert(['business_id' => $this->b98, 'product_id' => $pid, 'roteiro_id' => $rot, 'created_at' => now(), 'updated_at' => now()]);
    };
});

it('UC-PCRES-01 · venda aprovada: a OS do item sob encomenda reserva o insumo da receita, sem mexer no saldo', function () {
    ($this->linhaComRoteiro)($this->banner, $this->vBanner, 2);
    $ids = app(CriarOsDaVenda::class)->criar($this->b98, $this->venda);

    // À mão: receita 5 lona ÷ rende 2 = 2,5 por banner × 2 banners = 5,0000.
    $r = ($this->reserva)();
    expect($r)->toHaveCount(1);
    expect((string) $r[0]->qty_reserved)->toBe('5.0000')->and($r[0]->status)->toBe('active')
        ->and((int) $r[0]->pcp_ordem_id)->toBe($ids[0])->and((int) $r[0]->variation_id)->toBe($this->vLona)
        ->and((int) $r[0]->location_id)->toBe($this->localL)->and($r[0]->expires_at)->toBeNull();
    // 2º caminho (SQL) e o contrato da receita dão o mesmo número; o saldo físico não muda.
    expect(($this->reservado)())->toBe('5.0000');
    expect(app(InsumosDaReceita::class)->paraQuantidade($this->b98, $this->vBanner, '2.0000'))
        ->toBe([['product_id' => $this->lona, 'variation_id' => $this->vLona, 'quantidade' => '5.0000']]);
    expect(($this->saldo)())->toBe('10.0000');

    // Idempotente: criar de novo não reserva de novo.
    app(CriarOsDaVenda::class)->criar($this->b98, $this->venda);
    expect(($this->reserva)())->toHaveCount(1)->and(($this->reservado)())->toBe('5.0000');
});

it('UC-PCRES-02 · revenda (produto que controla estoque) e chave desligada não reservam nada', function () {
    // Revenda: a placa controla estoque e tem receita — o núcleo baixa a peça na venda; nada a reservar.
    ($this->linhaComRoteiro)($this->placa, $this->vPlaca, 2);
    expect(app(CriarOsDaVenda::class)->criar($this->b98, $this->venda))->toHaveCount(1);
    expect(($this->reserva)())->toHaveCount(0)->and(($this->saldo)())->toBe('10.0000');

    // Chave desligada (o padrão no deploy): a OS nasce, a reserva não.
    DB::table('business')->where('id', $this->b98)->update(['common_settings' => json_encode([])]);
    ($this->linhaComRoteiro)($this->banner, $this->vBanner, 2);
    expect(app(CriarOsDaVenda::class)->criar($this->b98, $this->venda))->toHaveCount(2);
    expect(($this->reserva)())->toHaveCount(0)->and(($this->saldo)())->toBe('10.0000');
});

it('UC-PCRES-03 · apontou 3 de 5 reservados: o saldo cai 3 e a reserva fica com 2', function () {
    [, $etapa] = ($this->novaOs)();
    expect(($this->saldo)())->toBe('10.0000')->and(($this->reservado)())->toBe('5.0000');

    ($this->baixar)($etapa, '3');

    // À mão: saldo 10 − 3 = 7; reservado 5 − 3 = 2. Uma baixa só: o saldo cai exatamente o apontado.
    expect(($this->saldo)())->toBe('7.0000')->and(($this->reservado)())->toBe('2.0000');
    expect((string) DB::table('pcp_consumos')->where('business_id', $this->b98)->sum('quantidade'))->toBe('3.0000');
    expect(($this->reserva)()[0]->status)->toBe('active');
});

it('UC-PCRES-04 · consumo real além do reservado baixa normalmente; a reserva acaba em zero', function () {
    [, $etapa] = ($this->novaOs)();
    ($this->baixar)($etapa, '3');
    ($this->baixar)($etapa, '4');

    // À mão: saldo 10 − 3 − 4 = 3 (7 consumidos > 5 reservados); a reserva abate só o que tinha: 5 → 0.
    expect(($this->saldo)())->toBe('3.0000')->and(($this->reservado)())->toBe('0');
    $r = ($this->reserva)()[0];
    expect($r->status)->toBe('consumed')->and((string) $r->qty_reserved)->toBe('0.0000');
});

it('UC-PCRES-05 · a OS concluída libera o que sobrou da reserva, sem mexer no saldo', function () {
    [$ordem, $etapa, $inicio] = ($this->novaOs)();
    ($this->baixar)($etapa, '3');
    app(ApontamentoOperadorService::class)->concluir($this->b98, $this->op1->id, $inicio->id, 'c-'.uniqid(), now());

    expect($ordem->fresh()->currentStage->key)->toBe('concluida');
    // À mão: sobraram 2 reservados → liberados (status released, quantidade preservada); saldo segue 7.
    $r = ($this->reserva)()[0];
    expect($r->status)->toBe('released')->and((string) $r->qty_reserved)->toBe('2.0000');
    expect(($this->reservado)())->toBe('0')->and(($this->saldo)())->toBe('7.0000');
});

it('UC-PCRES-06 · a venda cancelada libera todas as reservas das OS dela', function () {
    ($this->novaOs)();
    expect(($this->reservado)())->toBe('5.0000');

    $ids = app(AplicarModelo::class)->aplicar($this->b98, 'cv-pequena');
    $cancelado = SaleProcessStage::where('process_id', $ids['venda'])->where('is_cancelled', true)->firstOrFail();
    $tx = Transaction::withoutGlobalScopes()->findOrFail($this->venda); // SUPERADMIN: fixture da empresa 98.
    FsmAuthorizationFlag::mark(Transaction::class, $tx->id);
    $tx->current_stage_id = $cancelado->id;
    $tx->save();

    // À mão: 5 reservados → 0 ativos; saldo intacto (10), porque nada foi apontado.
    expect(($this->reserva)()[0]->status)->toBe('released');
    expect(($this->reservado)())->toBe('0')->and(($this->saldo)())->toBe('10.0000');
});

it('UC-PCRES-07 · Tier 0: a 99 não lê a receita, não abate nem libera reserva da 98', function () {
    [$ordem] = ($this->novaOs)();

    expect(app(InsumosDaReceita::class)->paraQuantidade($this->b99, $this->vBanner, '2.0000'))->toBe([]);
    expect($this->svc->abater($this->b99, $ordem->id, $this->vLona, '5'))->toBeNull();
    expect($this->svc->liberar($this->b99, $ordem->id))->toBe(0)->and($this->svc->liberarDaVenda($this->b99, $this->venda))->toBe(0);
    // Controle positivo: a mesma chamada na 98 abate.
    expect(($this->reservado)())->toBe('5.0000');
    expect($this->svc->abater($this->b98, $ordem->id, $this->vLona, '1'))->toBe('4.0000');
    expect(StockReservation::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $this->b99)->where('pcp_ordem_id', $ordem->id)->count())->toBe(0);
});
