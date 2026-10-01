<?php

declare(strict_types=1);

use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Modules\Ponto\Tests\Feature\PontoTestCase;
use Spatie\Permission\Models\Permission;

uses(PontoTestCase::class);

/**
 * API de Tarefas do app das lojas (oimpresso-app) — GET /api/app/tarefas e
 * POST /api/app/tarefas/todo/{id}/concluir.
 *
 * Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §3 — D11 ([W]): Tarefas = ToDo +
 * justificativas do Ponto; gestor vê as pendentes da empresa, colaborador só as próprias.
 * NÃO derivado do controller.
 *
 * Tier 0: tenant fictício 98 contra o business 2 da lane (ADR 0358). Transação revertida.
 * Cada "não aparece" tem o controle positivo no mesmo caso (anti-vácuo).
 */

const ATA_BIZ = 98;
const ATA_OUTRO = 2;

function ataUsuario(int $biz, bool $colaborador = true): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'ATA', 'last_name' => 'U' . uniqid(), 'username' => 'ata_' . uniqid(), 'password' => 'x',
        'business_id' => $biz, 'created_at' => now(), 'updated_at' => now(),
    ]);
    if ($colaborador) {
        DB::table('ponto_colaborador_config')->insert([
            'business_id' => $biz, 'user_id' => $id, 'matricula' => 'ATA-' . uniqid(),
            'controla_ponto' => true, 'admissao' => '2020-01-01', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return User::findOrFail($id);
}

function ataTodo(int $biz, int $criadoPor, ?int $atribuidoA = null, string $status = 'new'): int
{
    $id = DB::table('essentials_to_dos')->insertGetId([
        'business_id' => $biz, 'task' => 'Tarefa ATA ' . uniqid(), 'date' => now()->subDay(),
        'status' => $status, 'priority' => 'high', 'created_by' => $criadoPor,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    if ($atribuidoA) {
        DB::table('essentials_todos_users')->insert(['todo_id' => $id, 'user_id' => $atribuidoA]);
    }

    return $id;
}

function ataJustificativa(int $biz, User $u, string $estado = 'PENDENTE'): string
{
    $colab = DB::table('ponto_colaborador_config')->where('user_id', $u->id)->value('id');
    $id = (string) Str::uuid();
    DB::table('ponto_intercorrencias')->insert([
        'id' => $id, 'business_id' => $biz, 'colaborador_config_id' => $colab,
        'codigo' => 'INC-ATA-' . substr(uniqid(), -8), 'tipo' => 'ESQUECIMENTO_MARCACAO',
        'data' => now()->toDateString(), 'justificativa' => 'teste', 'estado' => $estado,
        'solicitante_id' => $u->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return $id;
}

function ataIds($teste): array
{
    return collect($teste->getJson('/api/app/tarefas')->assertOk()->json('itens'))->pluck('id')->all();
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema UltimatePOS exige MySQL (ADR 0358).');
    }
    if (DB::table('business')->whereIn('id', [ATA_BIZ, ATA_OUTRO])->count() !== 2) {
        $this->markTestSkipped('Tenants 98/2 ausentes nesta lane.');
    }
    DB::beginTransaction();

    // A empresa "tem" o Essentials no pacote (o seed da lane não monta assinatura).
    $mu = Mockery::mock(ModuleUtil::class)->makePartial();
    $mu->shouldReceive('hasThePermissionInSubscription')->andReturn(true);
    $this->app->instance(ModuleUtil::class, $mu);
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

it('sem token responde 401', function () {
    $this->getJson('/api/app/tarefas')->assertStatus(401);
});

it('ToDo: vejo a atribuída a mim e não a concluída nem a de outro business', function () {
    $eu = ataUsuario(ATA_BIZ);
    $minha = ataTodo(ATA_BIZ, $eu->id, $eu->id);
    $concluida = ataTodo(ATA_BIZ, $eu->id, $eu->id, 'completed');
    $outro = ataUsuario(ATA_OUTRO);
    $alheia = ataTodo(ATA_OUTRO, $outro->id, $outro->id);
    Passport::actingAs($eu, [], 'api');

    $ids = ataIds($this);
    expect($ids)->toContain('todo:' . $minha);
    expect($ids)->not->toContain('todo:' . $concluida);
    expect($ids)->not->toContain('todo:' . $alheia);

    $item = collect($this->getJson('/api/app/tarefas?origem=todo')->json('itens'))->firstWhere('id', 'todo:' . $minha);
    expect($item['atrasado'])->toBeTrue();
    expect($item['grupo'])->toBe('atrasadas');
});

it('Ponto: colaborador vê só a própria justificativa pendente; gestor vê as da empresa; nunca de outro business', function () {
    $eu = ataUsuario(ATA_BIZ);
    $colega = ataUsuario(ATA_BIZ);
    $minha = ataJustificativa(ATA_BIZ, $eu);
    $doColega = ataJustificativa(ATA_BIZ, $colega);
    $aprovada = ataJustificativa(ATA_BIZ, $eu, 'APROVADA');
    $deFora = ataJustificativa(ATA_OUTRO, ataUsuario(ATA_OUTRO));

    Passport::actingAs($eu, [], 'api');
    $ids = ataIds($this);
    expect($ids)->toContain('ponto:' . $minha);
    expect($ids)->not->toContain('ponto:' . $doColega);
    expect($ids)->not->toContain('ponto:' . $aprovada);

    $gestor = ataUsuario(ATA_BIZ, false);
    Permission::firstOrCreate(['name' => 'ponto.access', 'guard_name' => 'web']);
    $gestor->givePermissionTo('ponto.access');
    Passport::actingAs($gestor, [], 'api');
    $ids = ataIds($this);
    expect($ids)->toContain('ponto:' . $minha);
    expect($ids)->toContain('ponto:' . $doColega);
    expect($ids)->not->toContain('ponto:' . $deFora);
});

it('concluir: a minha ToDo vira completed; a de outro business dá 404 e não muda', function () {
    $eu = ataUsuario(ATA_BIZ);
    $minha = ataTodo(ATA_BIZ, $eu->id, $eu->id);
    $outro = ataUsuario(ATA_OUTRO);
    $alheia = ataTodo(ATA_OUTRO, $outro->id, $outro->id);
    Passport::actingAs($eu, [], 'api');

    $this->postJson('/api/app/tarefas/todo/' . $minha . '/concluir')->assertOk()->assertJsonPath('concluida', true);
    expect(DB::table('essentials_to_dos')->where('id', $minha)->value('status'))->toBe('completed');

    $this->postJson('/api/app/tarefas/todo/' . $alheia . '/concluir')->assertStatus(404);
    expect(DB::table('essentials_to_dos')->where('id', $alheia)->value('status'))->toBe('new');
});

it('empresa sem o módulo Essentials: ToDo some da lista e concluir responde 403', function () {
    $mu = Mockery::mock(ModuleUtil::class)->makePartial();
    $mu->shouldReceive('hasThePermissionInSubscription')->andReturn(false);
    $this->app->instance(ModuleUtil::class, $mu);

    $eu = ataUsuario(ATA_BIZ);
    $minha = ataTodo(ATA_BIZ, $eu->id, $eu->id);
    Passport::actingAs($eu, [], 'api');

    expect(ataIds($this))->not->toContain('todo:' . $minha);
    $this->postJson('/api/app/tarefas/todo/' . $minha . '/concluir')->assertStatus(403);
});
