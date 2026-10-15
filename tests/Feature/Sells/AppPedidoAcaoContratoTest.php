<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Tests\Contract\AutosaveContractRunner;

/**
 * POST /api/app/pedidos/{id}/acao — avançar a etapa do pedido pelo app (P1 · tela 22).
 *
 * Contrato: memory/requisitos/AppMobile/api/tela-22-avancar-etapa.md (só a lista segura,
 * `etapa_esperada` → 409, Idempotency-Key em app_idempotencia, ajuste `app_acoes_fsm_liberadas`
 * só estreita a lista). NÃO derivado do controller.
 *
 * Tier 0 (ADR 0093 / ADR 0358): pedido de OUTRO business → 404 e a etapa dele NÃO muda. Controle
 * positivo em par: o pedido do próprio business AVANÇA — senão o "não mudou" seria verde por vácuo.
 */
uses(DatabaseTransactions::class);

/**
 * Processo de teste no business: quote_approved --iniciar_producao--> in_production
 * --pausar_producao--> on_hold; cancelar_venda (com side effect) em quote_approved.
 *
 * @return array<string, int> chave do estágio → id
 */
function appAcaoProcesso(int $businessId): array
{
    $proc = DB::table('sale_processes')->insertGetId([
        'business_id' => $businessId, 'key' => 'app_acao_' . uniqid(), 'name' => 'App ação teste',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $st = [];
    foreach (['quote_approved' => false, 'in_production' => false, 'on_hold' => false, 'cancelled' => true] as $k => $term) {
        $st[$k] = DB::table('sale_process_stages')->insertGetId([
            'process_id' => $proc, 'key' => $k, 'name' => 'Etapa ' . $k, 'sort_order' => count($st),
            'is_terminal' => $term, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    $acao = fn (string $de, string $key, ?string $para, ?string $efeito = null) => DB::table('sale_stage_actions')->insert([
        'stage_id' => $st[$de], 'key' => $key, 'label' => 'Ação ' . $key,
        'target_stage_id' => $para ? $st[$para] : null, 'side_effect_class' => $efeito,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $acao('quote_approved', 'iniciar_producao', 'in_production');
    $acao('quote_approved', 'cancelar_venda', 'cancelled', 'App\\Domain\\Fsm\\SideEffects\\CancelarVendaCascade');
    $acao('in_production', 'pausar_producao', 'on_hold');

    return $st;
}

function appAcaoEtapa(int $vendaId): ?string
{
    return DB::table('transactions as t')->join('sale_process_stages as s', 's.id', '=', 't.current_stage_id')
        ->where('t.id', $vendaId)->value('s.key');
}

beforeEach(function () {
    if (! file_exists(storage_path('oauth-public.key'))) {
        \Illuminate\Support\Facades\Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS (ADR 0358).');
    }
    foreach (['transactions', 'sale_processes', 'sale_process_stages', 'sale_stage_actions', 'sale_stage_history', 'app_idempotencia'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}).");
        }
    }

    $ctx = AutosaveContractRunner::setupSellsContext($this);
    $this->biz = $ctx['business'];
    $this->user = $ctx['user'];
    $this->venda = (int) $ctx['transactionId'];
    $this->outroBiz = \App\Business::where('id', '!=', $this->biz->id)->first();
    if (! $this->outroBiz) {
        $this->markTestSkipped('Lane sem 2º business — contrato cross-tenant não exercitável.');
    }

    foreach (['direct_sell.view', 'access_all_locations'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        $this->user->givePermissionTo($p);
    }

    $this->st = appAcaoProcesso((int) $this->biz->id);
    DB::table('transactions')->where('id', $this->venda)->update([
        'status' => 'final', 'sub_type' => null, 'current_stage_id' => $this->st['quote_approved'],
    ]);
    // Sem ajuste gravado: vale o padrão (lista segura inteira).
    $pos = json_decode((string) DB::table('business')->where('id', $this->biz->id)->value('pos_settings'), true) ?: [];
    unset($pos['app_acoes_fsm_liberadas']);
    DB::table('business')->where('id', $this->biz->id)->update(['pos_settings' => json_encode($pos)]);

    Passport::actingAs($this->user, [], 'api');
});

$post = function (int $id, array $corpo, ?string $chave = 'k-') {
    $h = $chave === null ? [] : ['Idempotency-Key' => $chave === 'k-' ? 'k-' . uniqid() : $chave];

    return test()->withHeaders($h)->postJson('/api/app/pedidos/' . $id . '/acao', $corpo);
};

it('acoes[] do detalhe traz só as ações seguras — cancelar_venda (side effect) fica na web', function () {
    $chaves = collect($this->getJson('/api/app/pedidos/' . $this->venda)->assertOk()->json('acoes'))->pluck('chave');

    expect($chaves->all())->toContain('iniciar_producao');
    expect($chaves->all())->not->toContain('cancelar_venda');
});

it('avança a etapa pela FSM, devolve o detalhe na etapa nova e grava a trilha com origem app e motivo', function () use ($post) {
    $r = $post($this->venda, ['acao' => 'iniciar_producao', 'etapa_esperada' => 'quote_approved', 'motivo' => 'cliente pediu urgência'])
        ->assertOk();

    expect($r->json('id'))->toBe($this->venda);
    expect($r->json('etapa.chave'))->toBe('in_production');
    expect(appAcaoEtapa($this->venda))->toBe('in_production');

    $hist = DB::table('sale_stage_history')->where('transaction_id', $this->venda)->orderByDesc('id')->first();
    expect((int) $hist->from_stage_id)->toBe($this->st['quote_approved']);
    expect((int) $hist->to_stage_id)->toBe($this->st['in_production']);
    $payload = json_decode((string) $hist->payload_snapshot, true);
    expect($payload['origem'])->toBe('app');
    expect($payload['motivo'])->toBe('cliente pediu urgência');
});

it('Idempotency-Key: mesma chave e mesmo corpo devolvem a mesma resposta sem executar de novo; corpo diferente → 422', function () use ($post) {
    $corpo = ['acao' => 'iniciar_producao', 'etapa_esperada' => 'quote_approved'];
    $chave = 'idem-' . uniqid();

    $a = $post($this->venda, $corpo, $chave)->assertOk()->json();
    $b = $post($this->venda, $corpo, $chave)->assertOk()->json();

    expect($b)->toBe($a);
    expect(DB::table('sale_stage_history')->where('transaction_id', $this->venda)->count())->toBe(1);

    $post($this->venda, ['acao' => 'pausar_producao', 'etapa_esperada' => 'in_production'], $chave)
        ->assertStatus(422)->assertJsonPath('erro', 'idempotencia_conflito');
    expect(appAcaoEtapa($this->venda))->toBe('in_production');
});

it('etapa_esperada velha → 409 etapa_mudou e a etapa não muda', function () use ($post) {
    // Ação que EXISTE na etapa atual: sem a conferência de etapa_esperada ela executaria.
    $post($this->venda, ['acao' => 'iniciar_producao', 'etapa_esperada' => 'in_production'])
        ->assertStatus(409)->assertJsonPath('erro', 'etapa_mudou');

    expect(appAcaoEtapa($this->venda))->toBe('quote_approved');
    expect(DB::table('sale_stage_history')->where('transaction_id', $this->venda)->count())->toBe(0);
});

it('ação fora da lista segura (cancelar_venda) → 403 sem_permissao e a etapa não muda', function () use ($post) {
    $post($this->venda, ['acao' => 'cancelar_venda', 'etapa_esperada' => 'quote_approved'])
        ->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');

    expect(appAcaoEtapa($this->venda))->toBe('quote_approved');
});

it('ajuste app_acoes_fsm_liberadas estreita a lista: fora dele some de acoes[] e o POST → 403; chave insegura no ajuste não libera', function () use ($post) {
    $pos = json_decode((string) DB::table('business')->where('id', $this->biz->id)->value('pos_settings'), true) ?: [];
    $pos['app_acoes_fsm_liberadas'] = ['pausar_producao', 'cancelar_venda'];
    DB::table('business')->where('id', $this->biz->id)->update(['pos_settings' => json_encode($pos)]);

    $chaves = collect($this->getJson('/api/app/pedidos/' . $this->venda)->assertOk()->json('acoes'))->pluck('chave')->all();
    expect($chaves)->not->toContain('iniciar_producao');
    expect($chaves)->not->toContain('cancelar_venda');

    $post($this->venda, ['acao' => 'iniciar_producao', 'etapa_esperada' => 'quote_approved'])
        ->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');
    $post($this->venda, ['acao' => 'cancelar_venda', 'etapa_esperada' => 'quote_approved'])
        ->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');
    expect(appAcaoEtapa($this->venda))->toBe('quote_approved');
});

it('pedido de OUTRO business → 404 nao_encontrado e a etapa dele não muda', function () use ($post) {
    $stAlheio = appAcaoProcesso((int) $this->outroBiz->id);
    $alheia = DB::table('transactions')->insertGetId([
        'business_id' => $this->outroBiz->id, 'created_by' => $this->user->id, 'type' => 'sell',
        'status' => 'final', 'payment_status' => 'due', 'invoice_no' => 'APP-ACAO-ALHEIA-' . uniqid(),
        'transaction_date' => now(), 'total_before_tax' => 10, 'final_total' => 10,
        'current_stage_id' => $stAlheio['quote_approved'], 'created_at' => now(), 'updated_at' => now(),
    ]);

    $post($alheia, ['acao' => 'iniciar_producao', 'etapa_esperada' => 'quote_approved'])
        ->assertStatus(404)->assertJsonPath('erro', 'nao_encontrado');

    expect(appAcaoEtapa($alheia))->toBe('quote_approved');
    expect(DB::table('sale_stage_history')->where('transaction_id', $alheia)->count())->toBe(0);
});

it('sem Idempotency-Key, sem etapa_esperada ou com motivo > 500 → 422 validacao com o campo', function () use ($post) {
    $post($this->venda, ['acao' => 'iniciar_producao', 'etapa_esperada' => 'quote_approved'], null)
        ->assertStatus(422)->assertJsonPath('erro', 'validacao')->assertJsonStructure(['campos' => ['idempotency_key']]);
    $post($this->venda, ['acao' => 'iniciar_producao'])
        ->assertStatus(422)->assertJsonStructure(['campos' => ['etapa_esperada']]);
    $post($this->venda, ['acao' => 'iniciar_producao', 'etapa_esperada' => 'quote_approved', 'motivo' => str_repeat('a', 501)])
        ->assertStatus(422)->assertJsonStructure(['campos' => ['motivo']]);

    expect(appAcaoEtapa($this->venda))->toBe('quote_approved');
});

it('usuário sem nenhuma permissão de venda → 403 sem_permissao', function () use ($post) {
    $semPerm = \App\User::query()->whereKey(
        DB::table('users')->insertGetId([
            'first_name' => 'APP sem perm', 'username' => 'app_acao_sp_' . uniqid(), 'password' => 'x',
            'business_id' => $this->biz->id, 'created_at' => now(), 'updated_at' => now(),
        ])
    )->firstOrFail();
    Passport::actingAs($semPerm, [], 'api');

    $post($this->venda, ['acao' => 'iniciar_producao', 'etapa_esperada' => 'quote_approved'])
        ->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');
    expect(appAcaoEtapa($this->venda))->toBe('quote_approved');
});
