<?php

declare(strict_types=1);

use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\SideEffects\EmitirNovaAposCancelamento;
use App\Transaction;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\NfeBrasil\Models\NfeEmissao;

/**
 * US-SELL-029 — EmitirNovaAposCancelamento (SideEffect FSM).
 *
 * Specs FAILING-FIRST:
 *   1. Cria nova Transaction com mesmo business_id da parent (Tier 0)
 *   2. Linkage textual via additional_notes contém marker apontando pra original
 *   3. Clone de transaction_sell_lines preservado (1-pra-1 com FK nova)
 *   4. Bloqueia se subject não tem NFe cancelada via SEFAZ (InvalidArgumentException)
 *   5. Cross-tenant biz=99 — nova respeita business_id da parent (não vaza)
 *
 * Multi-tenant Tier 0 (ADR 0093) + biz=1 default + biz=99 cross-tenant (ADR 0101).
 *
 * Refs:
 *   - app/Domain/Fsm/SideEffects/EmitirNovaAposCancelamento.php
 *   - SPEC.md US-SELL-029
 *   - CONFAZ Ajuste SINIEF 07/2005 Art. 14
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    // ── Schema mínimo SQLite in-memory ─────────────────────────────────────

    // transactions — replica essencial UltimatePOS (sem FKs pra simplificar)
    Schema::create('transactions', function (Blueprint $t) {
        $t->increments('id');
        $t->integer('business_id')->unsigned()->index();
        $t->unsignedBigInteger('process_id')->nullable();
        $t->unsignedBigInteger('current_stage_id')->nullable();
        $t->string('type')->default('sell');
        $t->string('status')->default('final');
        $t->string('payment_status')->default('due');
        $t->integer('contact_id')->unsigned()->default(1);
        $t->string('invoice_no')->nullable();
        $t->string('ref_no')->nullable();
        $t->dateTime('transaction_date');
        $t->decimal('total_before_tax', 22, 4)->default(0);
        $t->decimal('tax_amount', 22, 4)->default(0);
        $t->decimal('discount_amount', 22, 4)->default(0);
        $t->decimal('shipping_charges', 22, 4)->default(0);
        $t->text('additional_notes')->nullable();
        $t->text('staff_note')->nullable();
        $t->decimal('final_total', 22, 4)->default(0);
        $t->integer('created_by')->unsigned()->default(1);
        $t->timestamps();
    });

    // transaction_sell_lines — clone alvo
    Schema::create('transaction_sell_lines', function (Blueprint $t) {
        $t->increments('id');
        $t->integer('transaction_id')->unsigned()->index();
        $t->integer('product_id')->unsigned()->default(1);
        $t->integer('variation_id')->unsigned()->default(1);
        $t->decimal('quantity', 22, 4)->default(1);
        $t->decimal('unit_price', 22, 4)->default(0);
        $t->decimal('item_tax', 22, 4)->default(0);
        $t->decimal('unit_price_inc_tax', 22, 4)->default(0);
        $t->timestamps();
    });

    // nfe_emissoes — pra validar pré-condição "NFe cancelada via SEFAZ"
    Schema::create('nfe_emissoes', function (Blueprint $t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->unsignedInteger('transaction_id')->nullable();
        $t->string('modelo', 2);
        $t->string('serie', 3);
        $t->unsignedInteger('numero');
        $t->string('chave_44', 44)->nullable();
        $t->string('status', 30)->default('pendente');
        $t->string('cstat', 5)->nullable();
        $t->text('motivo')->nullable();
        $t->string('xml_path', 255)->nullable();
        $t->string('danfe_path', 255)->nullable();
        $t->decimal('valor_total', 15, 2)->default(0);
        $t->dateTime('emitido_em')->nullable();
        $t->json('metadata')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });

    // sale_processes + sale_process_stages — FSM resolver de initial stage
    foreach (glob(database_path('migrations/2026_05_11_120*_create_sale_*.php')) ?: [] as $f) {
        (require $f)->up();
    }
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        foreach (array_reverse(glob(database_path('migrations/2026_05_11_120*_create_sale_*.php')) ?: []) as $f) {
            (require $f)->down();
        }
        foreach (['nfe_emissoes', 'transaction_sell_lines', 'transactions'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
});

// ── Helpers ────────────────────────────────────────────────────────────────

function emitirHelperSeedProcessoVendaComProducao(int $bizId): SaleProcessStage
{
    $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $bizId,
        'key' => 'venda_com_producao',
        'name' => 'Venda Com Produção',
        'default_for_contact_type' => 'any',
        'active' => true,
    ]);

    return SaleProcessStage::create([
        'process_id' => $process->id,
        'key' => 'quote_draft',
        'name' => 'Orçamento rascunho',
        'sort_order' => 1,
        'is_initial' => true,
    ]);
}

function emitirHelperCriarVendaCancelada(int $bizId, ?int $stageId = null): Transaction
{
    $tx = new Transaction();
    $tx->business_id = $bizId;
    $tx->current_stage_id = $stageId;
    $tx->type = 'sell';
    $tx->status = 'final';
    $tx->payment_status = 'paid';
    $tx->contact_id = 1;
    $tx->invoice_no = 'INV-001';
    $tx->ref_no = "REF-{$bizId}-001";
    $tx->transaction_date = now();
    $tx->total_before_tax = 100.00;
    $tx->final_total = 100.00;
    $tx->additional_notes = 'Venda original — cancelada via SEFAZ';
    $tx->created_by = 1;
    $tx->save();

    // 2 linhas de produto pra validar clone
    DB::table('transaction_sell_lines')->insert([
        ['transaction_id' => $tx->id, 'product_id' => 10, 'variation_id' => 100, 'quantity' => 2, 'unit_price' => 30, 'unit_price_inc_tax' => 33, 'created_at' => now(), 'updated_at' => now()],
        ['transaction_id' => $tx->id, 'product_id' => 20, 'variation_id' => 200, 'quantity' => 1, 'unit_price' => 40, 'unit_price_inc_tax' => 44, 'created_at' => now(), 'updated_at' => now()],
    ]);

    // NFe cancelada via SEFAZ — pré-condição validada pelo SideEffect
    NfeEmissao::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $bizId,
        'transaction_id' => $tx->id,
        'modelo' => '55',
        'serie' => '1',
        'numero' => 100,
        'chave_44' => str_pad('1', 44, '0', STR_PAD_LEFT),
        'status' => 'cancelada',
        'cstat' => '135',
        'valor_total' => 100.00,
    ]);

    return $tx;
}

// ─── Specs ────────────────────────────────────────────────────────────────

it('1. cria nova Transaction com mesmo business_id da parent (Tier 0)', function () {
    $stage = emitirHelperSeedProcessoVendaComProducao(1);
    $original = emitirHelperCriarVendaCancelada(1, $stage->id);

    (new EmitirNovaAposCancelamento())->execute($original, ['motivo' => 'Cliente pediu reemissão']);

    $novas = Transaction::where('business_id', 1)
        ->where('id', '!=', $original->id)
        ->get();

    expect($novas)->toHaveCount(1)
        ->and((int) $novas->first()->business_id)->toBe(1);
});

it('2. linkage textual via additional_notes contém marker da original', function () {
    $stage = emitirHelperSeedProcessoVendaComProducao(1);
    $original = emitirHelperCriarVendaCancelada(1, $stage->id);

    (new EmitirNovaAposCancelamento())->execute($original, ['motivo' => 'Reemissão pós-cancelamento']);

    $nova = Transaction::where('business_id', 1)
        ->where('id', '!=', $original->id)
        ->first();

    expect($nova->additional_notes)->toContain('[FSM:emitido_apos_cancelamento_de=tx_id=' . $original->id)
        ->and($nova->additional_notes)->toContain('ref_no=' . $original->ref_no)
        ->and($nova->additional_notes)->toContain('motivo=Reemissão pós-cancelamento');
});

it('3. clona transaction_sell_lines preservado (FK nova transaction)', function () {
    $stage = emitirHelperSeedProcessoVendaComProducao(1);
    $original = emitirHelperCriarVendaCancelada(1, $stage->id);

    expect(DB::table('transaction_sell_lines')->where('transaction_id', $original->id)->count())->toBe(2);

    (new EmitirNovaAposCancelamento())->execute($original, []);

    $nova = Transaction::where('business_id', 1)
        ->where('id', '!=', $original->id)
        ->first();

    $linhasNovas = DB::table('transaction_sell_lines')->where('transaction_id', $nova->id)->get();

    expect($linhasNovas)->toHaveCount(2)
        ->and((int) $linhasNovas[0]->product_id)->toBe(10)
        ->and((int) $linhasNovas[1]->product_id)->toBe(20);

    // Linhas originais permanecem intocadas
    expect(DB::table('transaction_sell_lines')->where('transaction_id', $original->id)->count())->toBe(2);
});

it('4. bloqueia se subject não tem NFe cancelada via SEFAZ (InvalidArgumentException)', function () {
    $stage = emitirHelperSeedProcessoVendaComProducao(1);

    // Venda sem NFe cancelada — só registro NfeEmissao com status=autorizada
    $tx = new Transaction();
    $tx->business_id = 1;
    $tx->current_stage_id = $stage->id;
    $tx->type = 'sell';
    $tx->status = 'final';
    $tx->payment_status = 'paid';
    $tx->contact_id = 1;
    $tx->ref_no = 'REF-SEM-CANCEL';
    $tx->transaction_date = now();
    $tx->total_before_tax = 100;
    $tx->final_total = 100;
    $tx->created_by = 1;
    $tx->save();

    NfeEmissao::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'transaction_id' => $tx->id,
        'modelo' => '55',
        'serie' => '1',
        'numero' => 200,
        'chave_44' => str_pad('2', 44, '0', STR_PAD_LEFT),
        'status' => 'autorizada', // ← NÃO cancelada
        'valor_total' => 100,
    ]);

    expect(fn () => (new EmitirNovaAposCancelamento())->execute($tx, []))
        ->toThrow(InvalidArgumentException::class, 'não tem NFe cancelada via SEFAZ');
});

it('5. cross-tenant biz=99 — nova respeita business_id da parent (Tier 0 isolation)', function () {
    // Seed processos em 2 bizs diferentes
    $stage1 = emitirHelperSeedProcessoVendaComProducao(1);
    $stage99 = emitirHelperSeedProcessoVendaComProducao(99);

    $original99 = emitirHelperCriarVendaCancelada(99, $stage99->id);

    // Mesmo se payload tentasse forçar business_id (não tenta, mas defesa em depth):
    // SideEffect SEMPRE deriva business_id de subject->business_id
    (new EmitirNovaAposCancelamento())->execute($original99, [
        'motivo' => 'Tentativa cross-tenant via payload',
        'business_id' => 1, // ← payload spoof tentaria mover pra biz=1
    ]);

    // Nova venda DEVE ter business_id=99 (da parent), NÃO biz=1 (do payload)
    $nova = Transaction::withoutGlobalScopes()
        ->where('id', '!=', $original99->id)
        ->where('business_id', 99)
        ->first();

    expect($nova)->not->toBeNull()
        ->and((int) $nova->business_id)->toBe(99);

    // E biz=1 NÃO recebeu vazamento
    $vazamento = Transaction::withoutGlobalScopes()
        ->where('business_id', 1)
        ->count();

    expect($vazamento)->toBe(0);
});
