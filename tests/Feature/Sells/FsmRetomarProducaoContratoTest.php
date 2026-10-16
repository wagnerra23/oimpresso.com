<?php

declare(strict_types=1);

use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use App\Domain\Fsm\Services\ExecuteStageActionService;
use App\Services\AppLojas\AcoesDoPedido;
use App\Transaction;
use Database\Seeders\FsmProcessoVendaComProducaoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Contract\AutosaveContractRunner;

/**
 * ADR 0434 (decisão [W] 2026-10-16, item 1) — `retomar_producao`: Em espera → Em produção, papel
 * producao.iniciar, não crítica, sem side effect. Pausar e retomar aceitam `motivo` opcional, gravado
 * em sale_stage_history.payload_snapshot. NÃO derivado do seeder: o contrato é a ADR.
 *
 * Usa o processo REAL (FsmProcessoVendaComProducaoSeeder::runForBusiness) no business do teste e a
 * migration de dados 2026_10_16_130000 que leva a ação a quem já tinha o processo.
 *
 * Tier 0 (ADR 0093 / ADR 0358): venda de outro business não retoma. Controle positivo em par: a do
 * próprio business retoma — senão o "não mudou" seria verde por vácuo.
 */
uses(DatabaseTransactions::class);

function retomarEtapas(int $bizId): array
{
    $proc = DB::table('sale_processes')->where('business_id', $bizId)->where('key', 'venda_com_producao')->value('id');

    return DB::table('sale_process_stages')->where('process_id', $proc)->pluck('id', 'key')->map(fn ($v) => (int) $v)->all();
}

function retomarPapel(int $bizId): string
{
    return Schema::hasColumn('roles', 'business_id') ? "producao.iniciar#{$bizId}" : 'producao.iniciar';
}

function retomarAcao(int $bizId): ?object
{
    return DB::table('sale_stage_actions')->where('stage_id', retomarEtapas($bizId)['on_hold'])
        ->where('key', 'retomar_producao')->first();
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
    $this->st = retomarEtapas($this->biz);
    DB::table('transactions')->where('id', $this->venda)->update([
        'status' => 'final', 'sub_type' => null, 'current_stage_id' => $this->st['on_hold'],
    ]);
});

it('o processo tem retomar_producao em on_hold → in_production, papel producao.iniciar, sem side effect e não crítica', function () {
    $acao = retomarAcao($this->biz);

    expect($acao)->not->toBeNull();
    expect((int) $acao->target_stage_id)->toBe($this->st['in_production']);
    expect($acao->side_effect_class)->toBeNull();
    expect($acao->event_class)->toBeNull();
    expect((bool) $acao->is_critical)->toBeFalse();
    expect(DB::table('sale_stage_action_roles')->where('action_id', $acao->id)->pluck('role_name')->all())
        ->toBe([retomarPapel($this->biz)]);
});

it('com o papel producao.iniciar, retomar volta a venda para in_production e grava o motivo no histórico', function () {
    $this->user->assignRole(Role::findByName(retomarPapel($this->biz), 'web'));

    app(ExecuteStageActionService::class)->execute(
        Transaction::findOrFail($this->venda), 'retomar_producao', $this->user, ['motivo' => 'material chegou']
    );

    expect((int) DB::table('transactions')->where('id', $this->venda)->value('current_stage_id'))->toBe($this->st['in_production']);
    $hist = DB::table('sale_stage_history')->where('transaction_id', $this->venda)->orderByDesc('id')->first();
    expect((int) $hist->from_stage_id)->toBe($this->st['on_hold']);
    expect((int) $hist->to_stage_id)->toBe($this->st['in_production']);
    expect(json_decode((string) $hist->payload_snapshot, true)['motivo'])->toBe('material chegou');
});

it('sem o papel, retomar é recusado e a venda continua em on_hold', function () {
    expect(fn () => app(ExecuteStageActionService::class)->execute(Transaction::findOrFail($this->venda), 'retomar_producao', $this->user))
        ->toThrow(UnauthorizedActionException::class);

    expect((int) DB::table('transactions')->where('id', $this->venda)->value('current_stage_id'))->toBe($this->st['on_hold']);
});

it('venda de outro business apontando para a etapa deste não retoma (Tier 0)', function () {
    $outro = \App\Business::where('id', '!=', $this->biz)->first();
    if (! $outro) {
        $this->markTestSkipped('Lane sem 2º business — contrato cross-tenant não exercitável.');
    }
    $this->user->assignRole(Role::findByName(retomarPapel($this->biz), 'web'));
    $alheia = DB::table('transactions')->insertGetId([
        'business_id' => $outro->id, 'created_by' => $this->user->id, 'type' => 'sell', 'status' => 'final',
        'payment_status' => 'due', 'invoice_no' => 'RETOMAR-ALHEIA-' . uniqid(), 'transaction_date' => now(),
        'total_before_tax' => 10, 'final_total' => 10, 'current_stage_id' => $this->st['on_hold'],
        'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(fn () => app(ExecuteStageActionService::class)->execute(Transaction::findOrFail($alheia), 'retomar_producao', $this->user))
        ->toThrow(UnauthorizedActionException::class);
    expect((int) DB::table('transactions')->where('id', $alheia)->value('current_stage_id'))->toBe($this->st['on_hold']);
    expect(DB::table('sale_stage_history')->where('transaction_id', $alheia)->count())->toBe(0);

    // Controle positivo: a venda do próprio business, mesmo usuário e papel, retoma.
    app(ExecuteStageActionService::class)->execute(Transaction::findOrFail($this->venda), 'retomar_producao', $this->user);
    expect((int) DB::table('transactions')->where('id', $this->venda)->value('current_stage_id'))->toBe($this->st['in_production']);
});

it('a migration de dados cria retomar_producao em quem já tinha o processo, com os papéis da iniciar_producao, e é idempotente', function () {
    $acao = retomarAcao($this->biz);
    DB::table('sale_stage_action_roles')->where('action_id', $acao->id)->delete();
    DB::table('sale_stage_actions')->where('id', $acao->id)->delete();
    expect(retomarAcao($this->biz))->toBeNull();

    $migration = require database_path('migrations/2026_10_16_130000_add_retomar_producao_to_venda_com_producao.php');
    $migration->up();
    $migration->up();

    $nova = retomarAcao($this->biz);
    expect($nova)->not->toBeNull();
    expect((int) $nova->target_stage_id)->toBe($this->st['in_production']);
    $papeisIniciar = DB::table('sale_stage_action_roles as r')->join('sale_stage_actions as a', 'a.id', '=', 'r.action_id')
        ->where('a.stage_id', $this->st['quote_approved'])->where('a.key', 'iniciar_producao')->pluck('r.role_name')->sort()->values()->all();
    expect(DB::table('sale_stage_action_roles')->where('action_id', $nova->id)->pluck('role_name')->sort()->values()->all())
        ->toBe($papeisIniciar);
    expect(DB::table('sale_stage_actions')->where('stage_id', $this->st['on_hold'])->where('key', 'retomar_producao')->count())->toBe(1);
});

it('pelo app: retomar_producao está na lista segura, pede motivo, e o POST /acao retoma gravando o motivo', function () {
    expect(AcoesDoPedido::SEGURAS)->toContain('retomar_producao');
    expect(AcoesDoPedido::pedeMotivo('retomar_producao'))->toBeTrue();
    expect(AcoesDoPedido::pedeMotivo('pausar_producao'))->toBeTrue();
    expect(AcoesDoPedido::pedeMotivo('iniciar_producao'))->toBeFalse();

    if (! Schema::hasTable('app_idempotencia')) {
        $this->markTestSkipped('Schema ausente (app_idempotencia).');
    }
    if (! file_exists(storage_path('oauth-public.key'))) {
        \Illuminate\Support\Facades\Artisan::call('passport:keys', ['--force' => true]);
    }
    foreach (['direct_sell.view', 'access_all_locations'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        $this->user->givePermissionTo($p);
    }
    $this->user->assignRole(Role::findByName(retomarPapel($this->biz), 'web'));
    $pos = json_decode((string) DB::table('business')->where('id', $this->biz)->value('pos_settings'), true) ?: [];
    unset($pos['app_acoes_fsm_liberadas']);
    DB::table('business')->where('id', $this->biz)->update(['pos_settings' => json_encode($pos)]);
    Passport::actingAs($this->user, [], 'api');

    $this->withHeaders(['Idempotency-Key' => 'retomar-' . uniqid()])
        ->postJson('/api/app/pedidos/' . $this->venda . '/acao', [
            'acao' => 'retomar_producao', 'etapa_esperada' => 'on_hold', 'motivo' => 'cliente liberou arte',
        ])->assertOk()->assertJsonPath('etapa.chave', 'in_production');

    $hist = DB::table('sale_stage_history')->where('transaction_id', $this->venda)->orderByDesc('id')->first();
    $payload = json_decode((string) $hist->payload_snapshot, true);
    expect($payload['origem'])->toBe('app');
    expect($payload['motivo'])->toBe('cliente liberou arte');
});
