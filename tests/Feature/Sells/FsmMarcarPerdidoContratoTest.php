<?php

declare(strict_types=1);

use App\Domain\Fsm\Exceptions\InvalidActionForCurrentStageException;
use App\Domain\Fsm\Modelos\AplicarModelo;
use App\Domain\Fsm\Services\ExecuteStageActionService;
use App\Transaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\Contract\AutosaveContractRunner;

/**
 * "Marcar perdido" na venda de modelo (decisão [W] 2026-10-21).
 *
 * Contrato vindo do protótipo Fluxos e regras, processo Orçamento e venda
 * (ProcessosDoPrototipo): "Enviado → Perdido · Marcar perdido · Motivo obrigatório · Vendedor".
 * Perdido é final e fica fora dos passos, como o Cancelado; só o modelo que tem a etapa Enviado
 * (cv-pcp) ganha a ação. "Alimenta o funil" não faz parte deste contrato.
 */
uses(DatabaseTransactions::class);

function perdPapel(int $bizId, string $papel): string
{
    return Schema::hasColumn('roles', 'business_id') ? "{$papel}#{$bizId}" : $papel;
}

/** @return array<string, int> chave da etapa => id */
function perdEtapas(int $processId): array
{
    return DB::table('sale_process_stages')->where('process_id', $processId)->pluck('id', 'key')->map(fn ($v) => (int) $v)->all();
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS (ADR 0358).');
    }
    if (! Schema::hasTable('fsm_modelos_empresa') || ! Schema::hasColumn('sale_stage_actions', 'exige_motivo')) {
        $this->markTestSkipped('Migrations do modelo de fluxo ausentes.');
    }

    $ctx = AutosaveContractRunner::setupSellsContext($this);
    $this->biz = (int) $ctx['business']->id;
    $this->user = $ctx['user'];
    $this->venda = (int) $ctx['transactionId'];

    $ids = app(AplicarModelo::class)->aplicar($this->biz, 'cv-pcp', 'media', 'cv');
    $this->processo = $ids['venda'];
    $this->st = perdEtapas($this->processo);
    DB::table('transactions')->where('id', $this->venda)->update([
        'process_id' => $this->processo, 'current_stage_id' => $this->st['enviado'],
    ]);
});

it('o modelo com Enviado ganha a etapa Perdido, final e fora dos passos, e a ação Marcar perdido do vendedor', function () {
    $perdido = DB::table('sale_process_stages')->find($this->st['perdido']);
    expect($perdido->name)->toBe('Perdido')
        ->and((bool) $perdido->is_terminal)->toBeTrue()
        ->and((bool) $perdido->is_cancelled)->toBeTrue()
        ->and($perdido->passo)->toBeNull();

    $acao = DB::table('sale_stage_actions')->where('stage_id', $this->st['enviado'])->where('key', 'marcar_perdido')->first();
    expect($acao)->not->toBeNull();
    expect($acao->label)->toBe('Marcar perdido')
        ->and((int) $acao->target_stage_id)->toBe($this->st['perdido'])
        ->and((bool) $acao->exige_motivo)->toBeTrue()
        ->and((bool) $acao->requires_confirmation)->toBeTrue()
        ->and((bool) $acao->is_critical)->toBeFalse()
        ->and(DB::table('sale_stage_action_roles')->where('action_id', $acao->id)->pluck('role_name')->all())
        ->toBe([perdPapel($this->biz, 'vendas.enviar')]);

    // Só sai de Enviado: as outras etapas não têm a ação.
    expect(DB::table('sale_stage_actions')->whereIn('stage_id', array_values($this->st))->where('key', 'marcar_perdido')->count())->toBe(1);
});

it('modelo sem a etapa Enviado não ganha Perdido', function () {
    $ids = app(AplicarModelo::class)->aplicar($this->biz, 'cv-pequena', 'pequena', 'cv');

    expect(perdEtapas($ids['venda']))->not->toHaveKey('perdido');
});

it('aplicar de novo não duplica a etapa nem a ação', function () {
    app(AplicarModelo::class)->aplicar($this->biz, 'cv-pcp', 'media', 'cv');

    expect(DB::table('sale_process_stages')->where('process_id', $this->processo)->where('key', 'perdido')->count())->toBe(1)
        ->and(DB::table('sale_stage_actions')->where('stage_id', $this->st['enviado'])->where('key', 'marcar_perdido')->count())->toBe(1);
});

it('sem motivo o motor recusa e a venda fica em Enviado; com motivo vai para Perdido e o motivo fica no histórico', function () {
    $this->user->assignRole(Role::findByName(perdPapel($this->biz, 'vendas.enviar'), 'web'));
    $svc = app(ExecuteStageActionService::class);

    foreach ([[], ['motivo' => '   ']] as $payload) {
        expect(fn () => $svc->execute(Transaction::findOrFail($this->venda), 'marcar_perdido', $this->user, $payload))
            ->toThrow(InvalidActionForCurrentStageException::class);
    }
    expect((int) DB::table('transactions')->where('id', $this->venda)->value('current_stage_id'))->toBe($this->st['enviado'])
        ->and(DB::table('sale_stage_history')->where('transaction_id', $this->venda)->count())->toBe(0);

    $svc->execute(Transaction::findOrFail($this->venda), 'marcar_perdido', $this->user, ['motivo' => 'Cliente fechou com outro fornecedor']);

    expect((int) DB::table('transactions')->where('id', $this->venda)->value('current_stage_id'))->toBe($this->st['perdido']);
    $hist = DB::table('sale_stage_history')->where('transaction_id', $this->venda)->first();
    expect((int) $hist->to_stage_id)->toBe($this->st['perdido'])
        ->and(json_decode((string) $hist->payload_snapshot, true)['motivo'] ?? null)->toBe('Cliente fechou com outro fornecedor');
});

it('a gaveta da venda recebe exige_motivo e o POST sem motivo volta 422', function () {
    $this->user->assignRole(Role::findByName(perdPapel($this->biz, 'vendas.enviar'), 'web'));
    $sessao = ['user.business_id' => $this->biz, 'business.id' => $this->biz, 'user.id' => $this->user->id];

    $acoes = $this->actingAs($this->user)->withSession($sessao)->getJson("/api/sells/{$this->venda}/fsm-actions")->assertOk()->json('actions');
    $perder = collect($acoes)->firstWhere('key', 'marcar_perdido');
    expect($perder)->not->toBeNull();
    expect($perder['exige_motivo'])->toBeTrue()
        ->and($perder['target_stage']['name'])->toBe('Perdido');

    $resp = $this->actingAs($this->user)->withSession($sessao)
        ->postJson("/sells/{$this->venda}/fsm-action", ['action_key' => 'marcar_perdido', 'payload' => []]);
    $resp->assertStatus(422);
    expect((string) $resp->json('error'))->toContain('motivo');
    expect((int) DB::table('transactions')->where('id', $this->venda)->value('current_stage_id'))->toBe($this->st['enviado']);
});
