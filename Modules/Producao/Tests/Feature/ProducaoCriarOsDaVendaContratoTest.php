<?php

declare(strict_types=1);
// Cobre UC-FLX-01 (venda aprovada vira OS sem redigitar) e UC-FLX-07 (etapas vêm do produto) — ADR 0436, PR 3.

use App\Domain\Fsm\Modelos\AplicarModelo;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageHistory;
use App\Domain\Fsm\Services\ExecuteStageActionService;
use App\Scopes\ScopeByBusiness;
use App\Transaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Entities\Ordem;
use Modules\Producao\Entities\Programacao;
use Modules\Producao\Services\CriarOsDaVenda;
use Modules\Producao\Services\ProcessoDaOrdem;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Fonte: protótipo aprovado pelo [W] em 16/10/2026 (#9517) — processo "venda" de
 * fluxos-processos.jsx ("Cliente aprova → cria a OS por item") e a vista Roteiros. Tenant 98 × 99.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS.');
    }
    if (! Schema::hasTable('pcp_ordens')) {
        $this->markTestSkipped('pcp_ordens ausente — migrations do Modules/Producao não rodaram.');
    }
    $this->B98 = $this->seededTenant();
    $this->b98 = (int) $this->B98->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->vendedor = $this->usuarioComPermissoes(['sell.view'], $this->B98);
});

/** Roteiro com bloco paralelo (2 trilhas que se juntam) e uma etapa opcional. */
function osRoteiro(int $biz): int
{
    $id = DB::table('pcp_roteiros')->insertGetId(['business_id' => $biz, 'familia' => 'Projeto', 'nome' => 'Fachada '.uniqid(), 'ativo' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $e = fn ($bloco, $trilha, $ordem, $nome, $horas, $junta = null, $opc = null) => ['business_id' => $biz, 'roteiro_id' => $id, 'bloco' => $bloco,
        'trilha' => $trilha, 'ordem' => $ordem, 'nome' => $nome, 'horas' => $horas, 'junta' => $junta, 'opcional_por' => $opc, 'created_at' => now(), 'updated_at' => now()];
    DB::table('pcp_roteiro_etapas')->insert([
        $e(1, 0, 0, 'Arte', 1), $e(2, 1, 0, 'Estrutura', 6, 'Montagem'), $e(2, 2, 0, 'Adesivo', 1, 'Montagem'),
        $e(2, 2, 1, 'Laminação', 0.5, 'Montagem', 'com laminação'), $e(3, 0, 0, 'Montagem', 2),
    ]);

    return $id;
}

/** Unidade + produto + variação na empresa (mesmo formato do fixture do Manufacturing). @return array{0:int,1:int} */
function osProduto(int $biz, int $userId, string $nome): array
{
    $suf = random_int(100000, 999999);
    $unit = DB::table('units')->insertGetId(['business_id' => $biz, 'actual_name' => 'Unidade OS', 'short_name' => 'UO', 'allow_decimal' => 1,
        'created_by' => $userId, 'created_at' => now(), 'updated_at' => now()]);
    $prod = DB::table('products')->insertGetId(['business_id' => $biz, 'name' => $nome, 'type' => 'single', 'unit_id' => $unit, 'tax_type' => 'exclusive',
        'sku' => 'OSI-'.$suf, 'barcode_type' => 'C128', 'enable_stock' => 0, 'alert_quantity' => 0, 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now()]);
    $pv = DB::table('product_variations')->insertGetId(['product_id' => $prod, 'name' => 'DUMMY', 'is_dummy' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $var = DB::table('variations')->insertGetId(['name' => 'DUMMY', 'product_id' => $prod, 'product_variation_id' => $pv, 'sub_sku' => 'OSI-'.$suf,
        'default_purchase_price' => 0, 'dpp_inc_tax' => 0, 'profit_percent' => 0, 'default_sell_price' => 0, 'sell_price_inc_tax' => 0, 'created_at' => now(), 'updated_at' => now()]);

    return [$prod, $var];
}

/** Venda no processo de venda do modelo, na etapa Orçamento, com 2 linhas: uma com roteiro e outra sem. */
function osVenda($teste, string $modelo): Transaction
{
    $ids = app(AplicarModelo::class)->aplicar($teste->b98, $modelo);
    $teste->vendedor->assignRole('vendas.enviar#'.$teste->b98);
    $orc = SaleProcessStage::where('process_id', $ids['venda'])->where('is_initial', true)->firstOrFail();
    $tx = new Transaction();
    foreach (['business_id' => $teste->b98, 'current_stage_id' => $orc->id, 'type' => 'sell', 'status' => 'final', 'payment_status' => 'due',
        'contact_id' => 1, 'invoice_no' => 'OSI-'.uniqid(), 'transaction_date' => now(), 'total_before_tax' => 100, 'final_total' => 100,
        'created_by' => $teste->vendedor->id] as $k => $v) {
        $tx->{$k} = $v;
    }
    $tx->save();
    [$p1, $v1] = osProduto($teste->b98, (int) $teste->vendedor->id, 'Fachada ACM');
    [$p2, $v2] = osProduto($teste->b98, (int) $teste->vendedor->id, 'Instalação');
    $teste->produtoComRoteiro = $p1;
    DB::table('transaction_sell_lines')->insert([
        ['transaction_id' => $tx->id, 'product_id' => $p1, 'variation_id' => $v1, 'quantity' => 2, 'unit_price' => 30, 'unit_price_inc_tax' => 33, 'created_at' => now(), 'updated_at' => now()],
        ['transaction_id' => $tx->id, 'product_id' => $p2, 'variation_id' => $v2, 'quantity' => 1, 'unit_price' => 40, 'unit_price_inc_tax' => 44, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('pcp_produto_roteiro')->insert(['business_id' => $teste->b98, 'product_id' => $p1, 'roteiro_id' => osRoteiro($teste->b98), 'created_at' => now(), 'updated_at' => now()]);

    return $tx;
}

function osOrdens(int $biz, int $txId)
{
    // SUPERADMIN: o teste confere a empresa explicitamente.
    return Ordem::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $biz)->where('transaction_id', $txId)->get();
}

it('UC-FLX-01 · aprovar a venda cria uma OS só para o item com roteiro, em Aguardando liberação', function () {
    $tx = osVenda($this, 'cv-pequena');
    app(ExecuteStageActionService::class)->execute($tx, 'avancar', $this->vendedor);

    $ordens = osOrdens($this->b98, $tx->id);
    expect($tx->fresh()->current_stage_id)->not->toBeNull();
    expect($ordens)->toHaveCount(1);
    expect((int) $ordens[0]->product_id)->toBe($this->produtoComRoteiro);
    expect($ordens[0]->currentStage->name)->toBe('Aguardando liberação');
});

it('UC-FLX-07 · as programações vêm do roteiro: sem a opcional, e as duas trilhas começam juntas', function () {
    $tx = osVenda($this, 'cv-pequena');
    app(ExecuteStageActionService::class)->execute($tx, 'avancar', $this->vendedor);

    $prog = Programacao::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $this->b98)
        ->where('os_tipo', Ordem::OS_TIPO)->where('os_id', osOrdens($this->b98, $tx->id)[0]->id)->get()->keyBy('nome');
    expect($prog->keys()->sort()->values()->all())->toBe(['Adesivo', 'Arte', 'Estrutura', 'Montagem']);
    expect($prog->every(fn ($p) => $p->status === 'pendente'))->toBeTrue();
    expect($prog['Estrutura']->inicio_previsto->equalTo($prog['Adesivo']->inicio_previsto))->toBeTrue();
    expect($prog['Estrutura']->junta)->toBe('Montagem');
    // A junta começa quando a trilha mais longa (6 h) termina.
    expect($prog['Montagem']->inicio_previsto->equalTo($prog['Estrutura']->fim_previsto))->toBeTrue();
});

it('idempotente: criar de novo não duplica OS nem programações', function () {
    $tx = osVenda($this, 'cv-pequena');
    app(ExecuteStageActionService::class)->execute($tx, 'avancar', $this->vendedor);
    $antes = Programacao::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $this->b98)->where('os_tipo', Ordem::OS_TIPO)->count();

    app(CriarOsDaVenda::class)->criar($this->b98, (int) $tx->id);

    expect(osOrdens($this->b98, $tx->id))->toHaveCount(1);
    expect(Programacao::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $this->b98)->where('os_tipo', Ordem::OS_TIPO)->count())->toBe($antes);
});

it('Tier 0: a 99 não cria OS a partir da venda da 98', function () {
    $tx = osVenda($this, 'cv-pequena');

    expect(app(CriarOsDaVenda::class)->criar($this->b99, (int) $tx->id))->toBe([]);
    expect(osOrdens($this->b99, $tx->id))->toHaveCount(0);
    expect(osOrdens($this->b98, $tx->id))->toHaveCount(0);
});

it('modelo sem produção (Balcão) não cria OS ao aprovar', function () {
    $tx = osVenda($this, 'balcao');
    app(ExecuteStageActionService::class)->execute($tx, 'avancar', $this->vendedor);

    expect(SaleProcessStage::find($tx->fresh()->current_stage_id)->key)->toBe('aprovado');
    expect(osOrdens($this->b98, $tx->id))->toHaveCount(0);
});

it('o histórico da venda não mostra transição de OS que tenha o mesmo número', function () {
    $tx = osVenda($this, 'cv-pequena');
    $etapaOs = app(ProcessoDaOrdem::class)->etapaInicial($this->b98);
    SaleStageHistory::withoutGlobalScope(ScopeByBusiness::class)->create(['business_id' => $this->b98, 'transaction_id' => $tx->id,
        'action_id' => null, 'from_stage_id' => null, 'to_stage_id' => $etapaOs->id, 'executed_at' => now()]);

    $daVenda = SaleStageHistory::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $this->b98)->where('transaction_id', $tx->id)->daVenda()->count();
    $todos = SaleStageHistory::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $this->b98)->where('transaction_id', $tx->id)->count();
    expect($todos - $daVenda)->toBe(1);
});
