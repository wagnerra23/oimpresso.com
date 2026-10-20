<?php
// @covers-us US-PCP-025

declare(strict_types=1);

use App\Domain\Fsm\Modelos\RegrasDaEmpresa;
use App\Domain\Fsm\Models\SaleStageHistory;
use App\TransactionPayment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Entities\Ordem;
use Modules\Producao\Entities\Posto;
use Modules\Producao\Entities\Programacao;
use Modules\Producao\Exceptions\ApontamentoRecusado;
use Modules\Producao\Services\ProcessoDaOrdem;
use Modules\Producao\Services\SinalDaOrdem;
use Modules\Producao\Services\SituacaoDaOrdem;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * UC-FLX-28 · US-PCP-025 — "Produção só libera com o sinal pago". Âncora: Apontamento/Index.casos.md
 * UC-PCLIB-04..07. Tenant 98 × 99 (ADR 0358). ⚠️ SKIP em SQLite: leia assertions (LC-13).
 *
 * VALOR (só leitura) — números feitos à mão e conferidos pelo serviço:
 *   venda 1.000,00 × 30% = 300,00 exigido · pago 299,99 → recusa · pago 300,00 → libera
 *   pago 350,00 de entrada − 50,00 de troco = 300,00 → libera
 *   venda 333,33 × 30% = 99,999 → 100,00 exigido · pago 99,99 → recusa
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS.');
    }
    if (! Schema::hasTable('pcp_ordens') || ! Schema::hasColumn('fsm_modelos_empresa', 'sinal_minimo_pct')) {
        $this->markTestSkipped('pcp_ordens / sinal_minimo_pct ausentes — migrations não rodaram.');
    }
    $this->B98 = $this->seededTenant();
    $this->b98 = (int) $this->B98->id;
    $this->B99 = $this->seededSupportClientTenant();
    $this->b99 = (int) $this->B99->id;
    app(ProcessoDaOrdem::class)->garantir($this->b98);
    $this->pcp = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $this->pcp->assignRole('producao.liberar#'.$this->b98);
    DB::table('fsm_modelos_empresa')->updateOrInsert(['business_id' => $this->b98], ['modelo' => 'cv-pequena', 'sinal_minimo_pct' => null, 'updated_at' => now()]);
    // SUPERADMIN: fixture da empresa 98.
    $this->posto = Posto::withoutGlobalScopes()->create(['business_id' => $this->b98, 'codigo' => 'P-'.uniqid(), 'nome' => 'Impressão',
        'tipo_custo' => 'pessoa', 'custo_hora' => '30.00']);

    $this->venda = fn (int $biz, string $total) => DB::table('transactions')->insertGetId(['business_id' => $biz, 'type' => 'sell', 'status' => 'final',
        'payment_status' => 'due', 'contact_id' => 1, 'invoice_no' => 'SIN-'.uniqid(), 'transaction_date' => now(), 'total_before_tax' => $total,
        'final_total' => $total, 'created_by' => $this->pcp->id, 'created_at' => now(), 'updated_at' => now()]);
    // Pagamento gravado sem o evento do Eloquent: o manual não dispara a liberação automática.
    $this->pagoSemEvento = fn (int $biz, int $tx, string $valor, int $troco = 0) => DB::table('transaction_payments')->insert(['business_id' => $biz,
        'transaction_id' => $tx, 'amount' => $valor, 'is_return' => $troco, 'method' => 'cash', 'paid_on' => now(), 'created_by' => $this->pcp->id,
        'created_at' => now(), 'updated_at' => now()]);
    $this->ordem = function (int $tx, ?int $responsavel) {
        $o = Ordem::withoutGlobalScopes()->create(['business_id' => $this->b98, 'transaction_id' => $tx, 'transaction_sell_line_id' => random_int(1000000, 9999999),
            'product_id' => 1, 'quantidade' => '1.0000', 'current_stage_id' => app(ProcessoDaOrdem::class)->etapaInicial($this->b98)->id]);
        Programacao::withoutGlobalScopes()->create(['business_id' => $this->b98, 'os_tipo' => Ordem::OS_TIPO, 'os_id' => $o->id, 'posto_id' => $this->posto->id,
            'bloco' => 1, 'nome' => 'Impressão', 'user_id' => $responsavel, 'inicio_previsto' => now(), 'fim_previsto' => now()->addHour(),
            'status' => 'pendente', 'created_by' => $this->pcp->id]);

        return $o;
    };
    $this->situacaoDe = fn (Ordem $o) => $o->fresh()->currentStage->key;
    $this->ligar = fn (?string $pct) => app(RegrasDaEmpresa::class)->mudarSinalMinimo($this->b98, $pct);
});

it('UC-PCLIB-04 · regra desligada: o Liberar segue como antes, sem nada pago', function () {
    $o = ($this->ordem)(($this->venda)($this->b98, '1000.00'), (int) $this->pcp->id);

    app(SituacaoDaOrdem::class)->liberar($this->b98, $o->id, $this->pcp);

    expect(($this->situacaoDe)($o))->toBe('na_fila');
});

it('UC-PCLIB-05 · regra ligada em 30%: sem pagamento ou com 299,99 o Liberar recusa, mesmo com producao.liberar', function () {
    ($this->ligar)('30');
    $tx = ($this->venda)($this->b98, '1000.00');
    $o = ($this->ordem)($tx, (int) $this->pcp->id);

    expect(fn () => app(SituacaoDaOrdem::class)->liberar($this->b98, $o->id, $this->pcp))
        ->toThrow(fn (ApontamentoRecusado $e) => expect($e->codigo)->toBe(ApontamentoRecusado::SINAL_PENDENTE)
            ->and($e->getMessage())->toBe('A empresa só libera a produção com 30,00% da venda pago: exigido 300,00, pago 0,00.'));

    ($this->pagoSemEvento)($this->b98, $tx, '299.99');
    expect(app(SinalDaOrdem::class)->situacao($this->b98, $tx, '30.00'))->toBe(['pago' => '299.99', 'exigido' => '300.00', 'ok' => false]);
    expect(fn () => app(SituacaoDaOrdem::class)->liberar($this->b98, $o->id, $this->pcp))->toThrow(ApontamentoRecusado::class, 'pago 299,99');
    expect(($this->situacaoDe)($o))->toBe('aguardando_liberacao');
});

it('UC-PCLIB-05 · o exigido arredonda ao centavo: 333,33 × 30% = 100,00, e 99,99 não basta', function () {
    ($this->ligar)('30');
    $tx = ($this->venda)($this->b98, '333.33');
    ($this->pagoSemEvento)($this->b98, $tx, '99.99');

    expect(app(SinalDaOrdem::class)->situacao($this->b98, $tx, '30.00'))->toBe(['pago' => '99.99', 'exigido' => '100.00', 'ok' => false]);
});

it('UC-PCLIB-06 · sinal alcançado (350,00 − 50,00 de troco = 300,00): o Liberar manual passa', function () {
    ($this->ligar)('30');
    $tx = ($this->venda)($this->b98, '1000.00');
    $o = ($this->ordem)($tx, (int) $this->pcp->id);
    ($this->pagoSemEvento)($this->b98, $tx, '350.00');
    ($this->pagoSemEvento)($this->b98, $tx, '50.00', 1);

    expect(app(SinalDaOrdem::class)->situacao($this->b98, $tx, '30.00'))->toBe(['pago' => '300.00', 'exigido' => '300.00', 'ok' => true]);
    app(SituacaoDaOrdem::class)->liberar($this->b98, $o->id, $this->pcp);

    expect(($this->situacaoDe)($o))->toBe('na_fila');
});

it('UC-PCLIB-07 · pagamento registrado libera a OS sozinho, pelo sistema, uma vez só', function () {
    ($this->ligar)('30');
    $tx = ($this->venda)($this->b98, '1000.00');
    $o = ($this->ordem)($tx, (int) $this->pcp->id);

    TransactionPayment::create(['business_id' => $this->b98, 'transaction_id' => $tx, 'amount' => '300.00', 'is_return' => 0, 'method' => 'cash',
        'paid_on' => now(), 'created_by' => $this->pcp->id]);

    expect(($this->situacaoDe)($o))->toBe('na_fila');
    $hist = SaleStageHistory::withoutGlobalScopes()->where('business_id', $this->b98)->where('transaction_id', $o->id)->latest('id')->first();
    expect($hist->payload_snapshot['ator'] ?? null)->toBe('sistema')
        ->and($hist->payload_snapshot['sinal_pago'] ?? null)->toBe('300.00')
        ->and($hist->payload_snapshot['sinal_exigido'] ?? null)->toBe('300.00');

    // Retorno repetido não executa a transição de novo.
    expect(app(SituacaoDaOrdem::class)->liberarPeloSinalDaVenda($this->b98, $tx))->toBe(0);
    expect(SaleStageHistory::withoutGlobalScopes()->where('business_id', $this->b98)->where('transaction_id', $o->id)->count())->toBe(1);
});

it('UC-PCLIB-07 · OS com etapa sem responsável fica aguardando, mesmo com o sinal pago', function () {
    ($this->ligar)('30');
    $tx = ($this->venda)($this->b98, '1000.00');
    $o = ($this->ordem)($tx, null);

    TransactionPayment::create(['business_id' => $this->b98, 'transaction_id' => $tx, 'amount' => '1000.00', 'is_return' => 0, 'method' => 'cash',
        'paid_on' => now(), 'created_by' => $this->pcp->id]);

    expect(($this->situacaoDe)($o))->toBe('aguardando_liberacao');
    expect(fn () => app(SituacaoDaOrdem::class)->liberarPeloSinal($this->b98, $o->id))->toThrow(ApontamentoRecusado::class, 'Escolha o responsável de: Impressão.');
});

it('UC-PCLIB-07 · Tier 0: pagamento de outra empresa nunca libera a OS, e a 99 não acha a OS da 98', function () {
    ($this->ligar)('30');
    $tx = ($this->venda)($this->b98, '1000.00');
    $o = ($this->ordem)($tx, (int) $this->pcp->id);
    $op99 = $this->usuarioComPermissoes(['producao.access'], $this->B99);

    // Pagamento gravado com a empresa 99 apontando a venda da 98: não conta e não libera.
    TransactionPayment::create(['business_id' => $this->b99, 'transaction_id' => $tx, 'amount' => '1000.00', 'is_return' => 0, 'method' => 'cash',
        'paid_on' => now(), 'created_by' => $op99->id]);

    expect(($this->situacaoDe)($o))->toBe('aguardando_liberacao');
    expect(app(SinalDaOrdem::class)->situacao($this->b98, $tx, '30.00')['pago'])->toBe('0.00');
    expect(fn () => app(SituacaoDaOrdem::class)->liberar($this->b99, $o->id, $op99))
        ->toThrow(fn (ApontamentoRecusado $e) => expect($e->codigo)->toBe(ApontamentoRecusado::NAO_ENCONTRADO));
});
