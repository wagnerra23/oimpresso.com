<?php

declare(strict_types=1);
// Cobre a guarda "Concluir: OS pronta ou entregue" do processo "venda" (fluxos-processos.jsx) — ADR 0436, PR 6.

use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use App\Domain\Fsm\Modelos\AplicarModelo;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Services\ExecuteStageActionService;
use App\Transaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Entities\Ordem;
use Modules\Producao\Services\OsDaVenda;
use Modules\Producao\Services\ProcessoDaOrdem;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Fonte: protótipo aprovado pelo [W] em 16/10/2026 (#9517) — detalhe da venda com "Concluir bloqueada
 * — só N de M OS prontas". Tenant 98 × 99 (ADR 0358). ⚠️ SKIP em SQLite (LC-13).
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
    $ids = app(AplicarModelo::class)->aplicar($this->b98, 'cv-pequena');
    $this->etapas = SaleProcessStage::where('process_id', $ids['venda'])->pluck('id', 'key');
    $this->vendedor = $this->usuarioComPermissoes(['sell.view'], $this->B98);
    $this->vendedor->assignRole('vendas.enviar#'.$this->b98);

    // Venda já Faturada (pula as etapas anteriores, que não são o caso aqui).
    $tx = new Transaction();
    foreach (['business_id' => $this->b98, 'current_stage_id' => $this->etapas['faturado'], 'type' => 'sell', 'status' => 'final',
        'payment_status' => 'due', 'contact_id' => 1, 'invoice_no' => 'VEO-'.uniqid(), 'transaction_date' => now(),
        'total_before_tax' => 100, 'final_total' => 100, 'created_by' => $this->vendedor->id] as $k => $v) {
        $tx->{$k} = $v;
    }
    $tx->save();
    $this->tx = $tx;
});

/** OS por item da venda, na situação pedida (direto no banco: a situação aqui é fixture). */
function veoOrdem(int $biz, int $txId, string $situacao): Ordem
{
    $p = app(ProcessoDaOrdem::class)->garantir($biz);
    // SUPERADMIN: fixture; o escopo é provado nos asserts.
    return Ordem::withoutGlobalScopes()->create(['business_id' => $biz, 'transaction_id' => $txId,
        'transaction_sell_line_id' => random_int(1000000, 9999999), 'product_id' => 1, 'quantidade' => '1.0000',
        'current_stage_id' => SaleProcessStage::where('process_id', $p->id)->where('key', $situacao)->value('id')]);
}

function veoEntregar($teste): void
{
    app(ExecuteStageActionService::class)->execute($teste->tx, 'avancar', $teste->vendedor);
}

it('com OS por terminar, a venda não passa para Entregue e fica em Faturado', function () {
    veoOrdem($this->b98, $this->tx->id, 'concluida');
    veoOrdem($this->b98, $this->tx->id, 'em_execucao');

    expect(fn () => veoEntregar($this))->toThrow(UnauthorizedActionException::class, '1 de 2 prontas');
    expect((int) $this->tx->fresh()->current_stage_id)->toBe((int) $this->etapas['faturado']);
});

it('com todas as OS prontas (cancelada não segura), a venda passa para Entregue', function () {
    veoOrdem($this->b98, $this->tx->id, 'concluida');
    veoOrdem($this->b98, $this->tx->id, 'cancelada');

    veoEntregar($this);
    expect((int) $this->tx->fresh()->current_stage_id)->toBe((int) $this->etapas['entregue']);
    expect(app(OsDaVenda::class)->placar($this->b98, $this->tx->id))->toBe(['total' => 1, 'prontas' => 1]);
});

it('venda sem OS passa para Entregue como antes', function () {
    veoEntregar($this);
    expect((int) $this->tx->fresh()->current_stage_id)->toBe((int) $this->etapas['entregue']);
});

it('Tier 0: OS de outra empresa com o mesmo número de venda não segura a venda da 98', function () {
    veoOrdem($this->b99, $this->tx->id, 'em_execucao');

    veoEntregar($this);
    expect((int) $this->tx->fresh()->current_stage_id)->toBe((int) $this->etapas['entregue']);
});

it('o legado não muda: Entregue de processo legado não consulta OS por item', function () {
    // SUPERADMIN: fixture do processo legado da 98.
    $proc = \App\Domain\Fsm\Models\SaleProcess::withoutGlobalScopes()->firstOrCreate(['business_id' => $this->b98, 'key' => 'venda_legado_teste_'.uniqid()],
        ['name' => 'Legado (teste)', 'dominio' => 'venda', 'modelo' => 'legado', 'default_for_contact_type' => 'any', 'active' => true]);
    $legado = SaleProcessStage::create(['process_id' => $proc->id, 'key' => 'entregue', 'name' => 'Entregue', 'sort_order' => 1, 'is_initial' => false]);
    veoOrdem($this->b98, $this->tx->id, 'em_execucao');

    expect(fn () => app(OsDaVenda::class)->guardar($this->b98, (int) $this->tx->id, (int) $legado->id))->not->toThrow(Exception::class);
    expect(fn () => app(OsDaVenda::class)->guardar($this->b98, (int) $this->tx->id, (int) $this->etapas['entregue']))
        ->toThrow(UnauthorizedActionException::class);
});
