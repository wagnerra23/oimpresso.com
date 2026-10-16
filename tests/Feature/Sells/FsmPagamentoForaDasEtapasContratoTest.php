<?php

declare(strict_types=1);

use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use App\Domain\Fsm\Services\ExecuteStageActionService;
use App\Services\Sells\SituacaoPagamentoVenda;
use App\Transaction;
use Database\Seeders\FsmProcessoVendaComProducaoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\Contract\AutosaveContractRunner;

/**
 * ADR 0434 item 2 (decisão [W] 2026-10-16) — pagamento sai da sequência de etapas.
 *
 * Contrato (da ADR, não do seeder): caminho padrão Faturada → Entregue → Concluída; `paid` e
 * `marcar_pago` ficam por compatibilidade (quem está em `paid` ainda entrega); "pago" é situação
 * dos títulos do Financeiro: sem_titulo | pendente | parcial | pago, com valor_aberto.
 *
 * Tier 0 (ADR 0093): título de outro business com o mesmo origem_id não conta.
 */
uses(DatabaseTransactions::class);

function pagEtapas(int $bizId): array
{
    $proc = DB::table('sale_processes')->where('business_id', $bizId)->where('key', 'venda_com_producao')->value('id');

    return DB::table('sale_process_stages')->where('process_id', $proc)->pluck('id', 'key')->map(fn ($v) => (int) $v)->all();
}

function pagPapel(int $bizId, string $papel): string
{
    return Schema::hasColumn('roles', 'business_id') ? "{$papel}#{$bizId}" : $papel;
}

function pagAcao(int $stageId, string $key): ?object
{
    return DB::table('sale_stage_actions')->where('stage_id', $stageId)->where('key', $key)->first();
}

function pagPapeisDa(?object $acao): array
{
    return $acao === null ? [] : DB::table('sale_stage_action_roles')->where('action_id', $acao->id)
        ->pluck('role_name')->sort()->values()->all();
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS (ADR 0358).');
    }
    foreach (['transactions', 'sale_processes', 'sale_process_stages', 'sale_stage_actions', 'sale_stage_action_roles', 'sale_stage_history', 'roles'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}).");
        }
    }

    $ctx = AutosaveContractRunner::setupSellsContext($this);
    $this->biz = (int) $ctx['business']->id;
    $this->user = $ctx['user'];
    $this->venda = (int) $ctx['transactionId'];

    app(FsmProcessoVendaComProducaoSeeder::class)->runForBusiness($this->biz);
    $this->st = pagEtapas($this->biz);
    DB::table('transactions')->where('id', $this->venda)->update([
        'status' => 'final', 'sub_type' => null, 'current_stage_id' => $this->st['invoiced'],
    ]);
});

it('o processo tem entregar em invoiced → delivered com os mesmos papéis da entregar de paid; marcar_pago e paid → entregar continuam', function () {
    $nova = pagAcao($this->st['invoiced'], 'entregar');
    $antiga = pagAcao($this->st['paid'], 'entregar');

    expect($nova)->not->toBeNull();
    expect((int) $nova->target_stage_id)->toBe($this->st['delivered']);
    expect($nova->side_effect_class)->toBeNull();
    expect(pagPapeisDa($nova))->toBe([pagPapel($this->biz, 'logistica.entregar')]);
    expect(pagPapeisDa($nova))->toBe(pagPapeisDa($antiga));
    expect(pagAcao($this->st['invoiced'], 'marcar_pago'))->not->toBeNull();
    expect((int) $antiga->target_stage_id)->toBe($this->st['delivered']);
});

it('caminho padrão: Faturada → Entregue → Concluída sem passar por Paga', function () {
    $this->user->assignRole(Role::findByName(pagPapel($this->biz, 'logistica.entregar'), 'web'));
    $this->user->assignRole(Role::findByName(pagPapel($this->biz, 'vendas.gerente'), 'web'));
    $svc = app(ExecuteStageActionService::class);

    $svc->execute(Transaction::findOrFail($this->venda), 'entregar', $this->user);
    expect((int) DB::table('transactions')->where('id', $this->venda)->value('current_stage_id'))->toBe($this->st['delivered']);
    $svc->execute(Transaction::findOrFail($this->venda), 'concluir', $this->user);
    expect((int) DB::table('transactions')->where('id', $this->venda)->value('current_stage_id'))->toBe($this->st['completed']);

    expect(DB::table('sale_stage_history')->where('transaction_id', $this->venda)->where('to_stage_id', $this->st['paid'])->count())->toBe(0);
});

it('entregar de Faturada exige o papel logistica.entregar', function () {
    expect(fn () => app(ExecuteStageActionService::class)->execute(Transaction::findOrFail($this->venda), 'entregar', $this->user))
        ->toThrow(UnauthorizedActionException::class);
    expect((int) DB::table('transactions')->where('id', $this->venda)->value('current_stage_id'))->toBe($this->st['invoiced']);
});

it('compatibilidade: pedido parado em Paga ainda entrega', function () {
    DB::table('transactions')->where('id', $this->venda)->update(['current_stage_id' => $this->st['paid']]);
    $this->user->assignRole(Role::findByName(pagPapel($this->biz, 'logistica.entregar'), 'web'));

    app(ExecuteStageActionService::class)->execute(Transaction::findOrFail($this->venda), 'entregar', $this->user);

    expect((int) DB::table('transactions')->where('id', $this->venda)->value('current_stage_id'))->toBe($this->st['delivered']);
});

it('a migration de dados cria entregar em invoiced copiando a de paid, e é idempotente', function () {
    $acao = pagAcao($this->st['invoiced'], 'entregar');
    DB::table('sale_stage_action_roles')->where('action_id', $acao->id)->delete();
    DB::table('sale_stage_actions')->where('id', $acao->id)->delete();
    expect(pagAcao($this->st['invoiced'], 'entregar'))->toBeNull();

    $migration = require database_path('migrations/2026_10_16_140000_add_entregar_from_invoiced_to_venda_com_producao.php');
    $migration->up();
    $migration->up();

    $nova = pagAcao($this->st['invoiced'], 'entregar');
    expect($nova)->not->toBeNull();
    expect((int) $nova->target_stage_id)->toBe($this->st['delivered']);
    expect(pagPapeisDa($nova))->toBe(pagPapeisDa(pagAcao($this->st['paid'], 'entregar')));
    expect(DB::table('sale_stage_actions')->where('stage_id', $this->st['invoiced'])->where('key', 'entregar')->count())->toBe(1);
});

it('situação de pagamento sai dos títulos do Financeiro: sem_titulo, pendente, parcial, pago; cancelado e outro business não contam', function () {
    if (! Schema::hasTable('fin_titulos')) {
        $this->markTestSkipped('Schema ausente (fin_titulos).');
    }
    $outro = \App\Business::where('id', '!=', $this->biz)->first();
    $novaVenda = fn () => (int) DB::table('transactions')->insertGetId([
        'business_id' => $this->biz, 'created_by' => $this->user->id, 'type' => 'sell', 'status' => 'final',
        'payment_status' => 'due', 'invoice_no' => 'PAG-' . uniqid(), 'transaction_date' => now(),
        'total_before_tax' => 150, 'final_total' => 150, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $titulo = fn (int $biz, int $venda, float $total, float $aberto, string $status, ?int $parcela = null) => DB::table('fin_titulos')->insert([
        'business_id' => $biz, 'numero' => 'PAG-' . strtoupper(Str::random(8)), 'tipo' => 'receber', 'status' => $status,
        'valor_total' => $total, 'valor_aberto' => $aberto, 'emissao' => now()->toDateString(),
        'vencimento' => now()->toDateString(), 'competencia_mes' => now()->format('Y-m'), 'origem' => 'venda',
        'origem_id' => $venda, 'parcela_numero' => $parcela, 'parcela_total' => $parcela ? 2 : null,
        'created_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    [$semTitulo, $pendente, $parcial, $pago] = [$novaVenda(), $novaVenda(), $novaVenda(), $novaVenda()];
    $titulo($this->biz, $semTitulo, 150, 150, 'cancelado');          // cancelado não conta
    $titulo($this->biz, $pendente, 150, 150, 'aberto');
    $titulo($this->biz, $parcial, 100, 0, 'quitado', 1);              // parcela 1 paga
    $titulo($this->biz, $parcial, 50, 50, 'aberto', 2);               // parcela 2 aberta → 50 em aberto
    $titulo($this->biz, $pago, 150, 0, 'quitado');
    if ($outro) {
        $titulo((int) $outro->id, $semTitulo, 999, 999, 'aberto');    // outro business, mesmo origem_id
    }

    $r = app(SituacaoPagamentoVenda::class)->dasVendas($this->biz, [$semTitulo, $pendente, $parcial, $pago]);

    expect($r[$semTitulo])->toBe(['situacao' => 'sem_titulo', 'valor_aberto' => 0.0]);
    expect($r[$pendente])->toBe(['situacao' => 'pendente', 'valor_aberto' => 150.0]);
    expect($r[$parcial])->toBe(['situacao' => 'parcial', 'valor_aberto' => 50.0]);
    expect($r[$pago])->toBe(['situacao' => 'pago', 'valor_aberto' => 0.0]);
    expect(app(SituacaoPagamentoVenda::class)->daVenda($this->biz, $parcial))->toBe($r[$parcial]);

    // Segundo caminho, independente do service: a soma crua no banco bate com o valor_aberto.
    $cru = (float) DB::table('fin_titulos')->where('business_id', $this->biz)->where('origem', 'venda')
        ->where('origem_id', $parcial)->where('status', '!=', 'cancelado')->sum('valor_aberto');
    expect(round($cru, 2))->toBe(50.0);
});
