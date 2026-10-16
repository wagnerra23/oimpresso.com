<?php

declare(strict_types=1);

use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;

/**
 * Aba Recorrências do Financeiro do app (tela 06, chave RECORRENCIAS):
 * GET /api/app/financeiro/recorrencias e POST /api/app/financeiro/recorrencias/{id}/interromper.
 *
 * Contrato: `interface Recorrencia` em oimpresso-app src/api.ts e a resposta da demo (src/demo.ts,
 * verRecorrencia) — NÃO derivado do controller. "Interromper" para a geração futura e não muda nenhum
 * lançamento já criado: o teste confere as faturas e as despesas filhas antes × depois.
 *
 * Tier 0 (ADR 0093): recorrência do business 2 não aparece e não se interrompe pelo 98 (404). Controle
 * positivo em par: as do próprio business aparecem.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! file_exists(storage_path('oauth-public.key'))) {
        \Illuminate\Support\Facades\Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS (ADR 0358).');
    }
    foreach (['rb_plans', 'rb_subscriptions', 'rb_invoices', 'transactions', 'expense_categories'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}).");
        }
    }
    $this->tenant = $this->seededTenant();
    $this->outro = \App\Business::where('id', '!=', $this->tenant->id)->orderBy('id')->first();
    if (! $this->outro) {
        $this->markTestSkipped('Lane sem 2º business — contrato cross-tenant não exercitável.');
    }
    foreach (['financeiro.access', 'recurringbilling.access', 'expense.edit'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $mu = Mockery::mock(ModuleUtil::class)->makePartial();
    $mu->shouldReceive('hasThePermissionInSubscription')->andReturnUsing(fn ($biz, $perm) => $perm === 'financeiro_module');
    app()->instance(ModuleUtil::class, $mu);
});

/** @param list<string> $permissoes */
function appRecUsuario(int $biz, array $permissoes): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'APP Rec', 'username' => 'app_rec_' . uniqid(), 'password' => 'x',
        'business_id' => $biz, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $u = User::findOrFail($id);
    foreach ($permissoes as $p) {
        $u->givePermissionTo($p);
    }

    return $u;
}

function appRecContato(int $biz, int $criador, string $nome): int
{
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => $nome, 'contact_status' => 'active',
        'created_by' => $criador, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Assinatura com N faturas já geradas. */
function appRecContrato(int $biz, int $contato, string $plano, float $valor, string $ciclo, string $status, string $inicio, string $proximo, int $faturas = 0): int
{
    $planoId = DB::table('rb_plans')->insertGetId([
        'business_id' => $biz, 'name' => $plano, 'slug' => 'app-rec-' . uniqid(), 'valor' => $valor, 'ciclo' => $ciclo,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $sub = DB::table('rb_subscriptions')->insertGetId([
        'business_id' => $biz, 'plan_id' => $planoId, 'contact_id' => $contato, 'status' => $status,
        'start_date' => $inicio, 'next_due_date' => $proximo, 'billing_anchor_date' => $proximo,
        'canceled_at' => $status === 'canceled' ? '2026-09-05 10:00:00' : null,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    for ($i = 0; $i < $faturas; $i++) {
        DB::table('rb_invoices')->insert([
            'business_id' => $biz, 'subscription_id' => $sub, 'contact_id' => $contato,
            'numero_documento' => 'APP-REC-' . strtoupper(Str::random(10)), 'valor' => $valor, 'status' => 'open',
            'vencimento' => $inicio, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return (int) $sub;
}

/** Despesa recorrente (pai) — filhas são as despesas que o gerador já criou. */
function appRecDespesa(int $biz, int $criador, string $notas, float $valor, string $data, array $extra = []): int
{
    return (int) DB::table('transactions')->insertGetId($extra + [
        'business_id' => $biz, 'type' => 'expense', 'status' => 'final', 'payment_status' => 'due',
        'transaction_date' => $data . ' 10:00:00', 'final_total' => $valor, 'total_before_tax' => $valor,
        'additional_notes' => $notas, 'created_by' => $criador, 'is_recurring' => 1, 'recur_interval' => 1,
        'recur_interval_type' => 'months', 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('sem token 401; sem acesso ao financeiro 403', function () {
    $this->getJson('/api/app/financeiro/recorrencias')->assertStatus(401);
    Passport::actingAs(appRecUsuario((int) $this->tenant->id, []), [], 'api');
    $this->getJson('/api/app/financeiro/recorrencias')->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');
    $this->postJson('/api/app/financeiro/recorrencias/1/interromper')->assertStatus(403);
});

it('contratos: formato da Recorrencia, faturas contadas, cancelado = interrompida, filtros contato e vigentes; outro business fora', function () {
    $biz = (int) $this->tenant->id;
    $eu = appRecUsuario($biz, ['financeiro.access']);
    Passport::actingAs($eu, [], 'api');
    $cli = appRecContato($biz, $eu->id, 'Clínica APP Rec');
    $outroCli = appRecContato($biz, $eu->id, 'Outro APP Rec');

    $ativo = appRecContrato($biz, $cli, 'Manutenção de fachada APP', 450.0, 'monthly', 'active', '2026-06-20', '2026-11-20', 4);
    $cancelado = appRecContrato($biz, $cli, 'Painel LED APP', 300.0, 'quarterly', 'canceled', '2026-03-10', '2026-12-10', 2);
    $deOutro = appRecContrato($biz, $outroCli, 'Licença APP', 1890.0, 'yearly', 'active', '2026-01-05', '2027-01-05');
    $alheio = appRecContrato((int) $this->outro->id, appRecContato((int) $this->outro->id, $eu->id, 'Alheio APP Rec'),
        'Alheio APP', 999.0, 'monthly', 'active', '2026-06-01', '2026-11-01');

    $r = $this->getJson("/api/app/financeiro/recorrencias?contato_id={$cli}")->assertOk();
    $itens = collect($r->json('itens'));
    expect($itens->pluck('id')->all())->toBe([$ativo, $cancelado]);
    expect($itens->firstWhere('id', $ativo))->toBe([
        'id' => $ativo, 'tipo' => 'receber', 'descricao' => 'Manutenção de fachada APP', 'parte' => 'Clínica APP Rec',
        'parte_id' => $cli, 'plano_nome' => null, 'valor' => 450, 'frequencia' => 'mensal', 'dia_geracao' => 20,
        'dia_vencimento' => 20, 'inicio' => '2026-06-20', 'termino' => null, 'proxima_geracao' => '2026-11-20',
        'proximo_vencimento' => '2026-11-20', 'gerados' => 4, 'status' => 'ativa', 'interrompida_em' => null,
        'contrato' => (string) $ativo,
    ]);
    expect($itens->firstWhere('id', $cancelado))->toMatchArray([
        'frequencia' => 'trimestral', 'gerados' => 2, 'status' => 'interrompida', 'interrompida_em' => '2026-09-05',
        'proxima_geracao' => null, 'proximo_vencimento' => null,
    ]);

    $vigentes = collect($this->getJson("/api/app/financeiro/recorrencias?contato_id={$cli}&vigentes=1")->assertOk()->json('itens'));
    expect($vigentes->pluck('id')->all())->toBe([$ativo]);

    $todas = collect($this->getJson('/api/app/financeiro/recorrencias')->assertOk()->json('itens'))->pluck('id')->all();
    expect($todas)->toContain($ativo, $cancelado, $deOutro);
    expect($todas)->not->toContain($alheio);
});

it('contas fixas: despesa recorrente com próxima geração pela regra do gerador, término pelas repetições, parada = interrompida', function () {
    $biz = (int) $this->tenant->id;
    $eu = appRecUsuario($biz, ['financeiro.access']);
    Passport::actingAs($eu, [], 'api');
    $cat = (int) DB::table('expense_categories')->insertGetId([
        'name' => 'Aluguel APP Rec', 'business_id' => $biz, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $pai = appRecDespesa($biz, $eu->id, 'Aluguel do galpão APP', 3500.0, '2026-08-10',
        ['expense_category_id' => $cat, 'subscription_repeat_on' => 10, 'recur_repetitions' => 3, 'ref_no' => 'CT-APP-14']);
    appRecDespesa($biz, $eu->id, 'Aluguel do galpão APP', 3500.0, '2026-09-10', ['recur_parent_id' => $pai, 'is_recurring' => 0]);
    $parada = appRecDespesa($biz, $eu->id, 'Energia APP', 800.0, '2026-07-05', ['recur_stopped_on' => '2026-09-01 08:00:00']);
    $alheia = appRecDespesa((int) $this->outro->id, $eu->id, 'Alheia APP', 10.0, '2026-07-05');

    $itens = collect($this->getJson('/api/app/financeiro/recorrencias')->assertOk()->json('itens'));
    $base = \App\Http\Controllers\Api\App\FinanceiroRecorrenciasController::ID_DESPESA;

    expect($itens->firstWhere('id', $base + $pai))->toBe([
        'id' => $base + $pai, 'tipo' => 'pagar', 'descricao' => 'Aluguel do galpão APP', 'parte' => null, 'parte_id' => null,
        'plano_nome' => 'Aluguel APP Rec', 'valor' => 3500, 'frequencia' => 'mensal', 'dia_geracao' => 10, 'dia_vencimento' => 10,
        'inicio' => '2026-08-10', 'termino' => '2026-11-10', 'proxima_geracao' => '2026-10-10', 'proximo_vencimento' => '2026-10-10',
        'gerados' => 2, 'status' => 'ativa', 'interrompida_em' => null, 'contrato' => 'CT-APP-14',
    ]);
    expect($itens->firstWhere('id', $base + $parada))->toMatchArray([
        'status' => 'interrompida', 'interrompida_em' => '2026-09-01', 'proxima_geracao' => null,
    ]);
    // A filha é lançamento gerado, não recorrência.
    expect($itens->pluck('descricao')->filter(fn ($d) => $d === 'Aluguel do galpão APP')->count())->toBe(1);
    expect($itens->pluck('id')->all())->not->toContain($base + $alheia);
});

it('interromper contrato: cancela a assinatura, não toca nas faturas já criadas, repete sem erro; sem permissão 403; alheio 404', function () {
    $biz = (int) $this->tenant->id;
    $eu = appRecUsuario($biz, ['financeiro.access', 'recurringbilling.access']);
    $cli = appRecContato($biz, $eu->id, 'Cliente APP Interromper');
    $sub = appRecContrato($biz, $cli, 'Contrato APP Interromper', 450.0, 'monthly', 'active', '2026-06-20', '2026-11-20', 3);
    $faturas = fn () => DB::table('rb_invoices')->where('subscription_id', $sub)->orderBy('id')
        ->get(['id', 'valor', 'status', 'vencimento', 'deleted_at'])->map(fn ($f) => (array) $f)->all();
    $antes = $faturas();

    // Só financeiro.access: vê, mas não interrompe.
    Passport::actingAs(appRecUsuario($biz, ['financeiro.access']), [], 'api');
    $this->postJson("/api/app/financeiro/recorrencias/{$sub}/interromper")->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');
    expect(DB::table('rb_subscriptions')->where('id', $sub)->value('status'))->toBe('active');

    Passport::actingAs($eu, [], 'api');
    $r = $this->postJson("/api/app/financeiro/recorrencias/{$sub}/interromper")->assertOk();
    expect($r->json())->toMatchArray(['id' => $sub, 'status' => 'interrompida', 'proxima_geracao' => null, 'gerados' => 3]);
    expect($r->json('interrompida_em'))->toBe(now()->toDateString());
    expect(DB::table('rb_subscriptions')->where('id', $sub)->value('status'))->toBe('canceled');
    expect($faturas())->toBe($antes);

    $this->postJson("/api/app/financeiro/recorrencias/{$sub}/interromper")->assertOk()->assertJsonPath('status', 'interrompida');
    expect($faturas())->toBe($antes);

    $alheio = appRecContrato((int) $this->outro->id, appRecContato((int) $this->outro->id, $eu->id, 'Alheio APP Int'),
        'Alheio APP Int', 10.0, 'monthly', 'active', '2026-06-01', '2026-11-01');
    $this->postJson("/api/app/financeiro/recorrencias/{$alheio}/interromper")->assertStatus(404)->assertJsonPath('erro', 'nao_encontrado');
    expect(DB::table('rb_subscriptions')->where('id', $alheio)->value('status'))->toBe('active');
});

it('interromper conta fixa: marca recur_stopped_on, não toca na despesa nem nas filhas; exige expense.edit; alheia 404', function () {
    $biz = (int) $this->tenant->id;
    $eu = appRecUsuario($biz, ['financeiro.access', 'expense.edit']);
    $pai = appRecDespesa($biz, $eu->id, 'Internet APP', 199.9, '2026-08-03');
    $filha = appRecDespesa($biz, $eu->id, 'Internet APP', 199.9, '2026-09-03', ['recur_parent_id' => $pai, 'is_recurring' => 0]);
    $base = \App\Http\Controllers\Api\App\FinanceiroRecorrenciasController::ID_DESPESA;
    $despesas = fn () => DB::table('transactions')->whereIn('id', [$pai, $filha])->orderBy('id')
        ->get(['id', 'final_total', 'status', 'payment_status', 'transaction_date'])->map(fn ($t) => (array) $t)->all();
    $antes = $despesas();

    Passport::actingAs(appRecUsuario($biz, ['financeiro.access']), [], 'api');
    $this->postJson('/api/app/financeiro/recorrencias/' . ($base + $pai) . '/interromper')->assertStatus(403);
    expect(DB::table('transactions')->where('id', $pai)->value('recur_stopped_on'))->toBeNull();

    Passport::actingAs($eu, [], 'api');
    $r = $this->postJson('/api/app/financeiro/recorrencias/' . ($base + $pai) . '/interromper')->assertOk();
    expect($r->json())->toMatchArray(['id' => $base + $pai, 'status' => 'interrompida', 'proxima_geracao' => null, 'gerados' => 2]);
    $parou = DB::table('transactions')->where('id', $pai)->value('recur_stopped_on');
    expect($parou)->not->toBeNull();
    expect($despesas())->toBe($antes);

    // Repetir não muda a data em que parou.
    $this->postJson('/api/app/financeiro/recorrencias/' . ($base + $pai) . '/interromper')->assertOk();
    expect(DB::table('transactions')->where('id', $pai)->value('recur_stopped_on'))->toBe($parou);

    $alheia = appRecDespesa((int) $this->outro->id, $eu->id, 'Alheia APP Int', 10.0, '2026-07-05');
    $this->postJson('/api/app/financeiro/recorrencias/' . ($base + $alheia) . '/interromper')->assertStatus(404);
    expect(DB::table('transactions')->where('id', $alheia)->value('recur_stopped_on'))->toBeNull();
});
