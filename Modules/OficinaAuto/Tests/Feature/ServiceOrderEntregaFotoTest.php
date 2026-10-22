<?php
// @covers-us US-OFICINA-047

declare(strict_types=1);

use App\Contracts\Oficina\AcoesOs;
use App\Http\Controllers\ServiceOrderFsmActionController;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Database\Seeders\OficinaAutoFsmSeeder;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Services\StageGateEvaluator;
use Spatie\Permission\Models\Permission;
use Tests\Support\WithSeededTenant;

uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

/**
 * US-OFICINA-047 · UC-FLX-22 — o veículo só é entregue com foto do serviço feito.
 *
 * Âncora: catálogo de fluxos, modelo "Oficina mecânica", processo de venda, etapa "Entregue ·
 * Fotos do serviço" (UC-FLX-22: "só conclui com foto do serviço e assinatura do cliente"). A
 * assinatura NÃO entra aqui — não existe na Oficina; o caso fica parcial.
 *
 * "Foto do serviço" = imagem anexada à OS (laudo) ou a item da vistoria, enviada a partir da
 * entrada da OS em `em_execucao` na trilha FSM. Foto do diagnóstico (antes) não conta.
 *
 * Arranjo, tenant 98 contra 99 (nunca biz=4):
 *   execução começou há 1 h · foto do laudo há 2 h (antes) · PDF há 10 min (não é foto)
 *   → entregar recusado; com uma imagem há 10 min → entrega; foto do tenant 99 apontando
 *   para a OS do 98 não conta.
 * ⚠️ SKIP em SQLite / sem schema: leia assertions, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS.');
    }
    foreach (['service_orders', 'vehicles', 'arquivos', 'sale_processes', 'sale_process_stages', 'sale_stage_history'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}).");
        }
    }
    try {
        $this->biz = (int) $this->seededTenant()->id;
        $this->outra = (int) $this->seededSupportClientTenant()->id;
    } catch (\Throwable $e) {
        $this->markTestSkipped('Tenant de teste ausente: ' . $e->getMessage());
    }
    (new OficinaAutoFsmSeeder())->runForBusiness($this->biz);
    (new OficinaAutoFsmSeeder())->runForBusiness($this->outra);
    session(['user.business_id' => $this->biz]);

    $this->etapa = fn (int $biz, string $key) => (int) DB::table('sale_process_stages as s')
        ->join('sale_processes as p', 'p.id', '=', 's.process_id')
        ->where('p.business_id', $biz)->where('p.key', 'oficina_mecanica_os')->where('s.key', $key)
        ->value('s.id');

    $veiculo = DB::table('vehicles')->insertGetId([
        'business_id' => $this->biz, 'plate' => 'FOT' . random_int(1000, 9999), 'vehicle_type' => 'caminhao',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->os = DB::table('service_orders')->insertGetId([
        'business_id' => $this->biz, 'vehicle_id' => $veiculo, 'order_type' => 'mecanica', 'status' => 'aberta',
        'current_stage_id' => ($this->etapa)($this->biz, 'pronto_retirada'),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    // Entrou em execução há 1 h (trilha FSM, como o ExecuteStageActionService grava).
    entregaFotoEntrouEmExecucao($this->biz, $this->os, ($this->etapa)($this->biz, 'em_execucao'), now()->subHour());
    // Foto do diagnóstico, antes da execução: não é foto do serviço.
    entregaFotoAnexo($this->biz, $this->os, 'image/jpeg', now()->subHours(2));
    // PDF depois da execução: o app aceita PDF, mas não é foto.
    entregaFotoAnexo($this->biz, $this->os, 'application/pdf', now()->subMinutes(10));

    Permission::firstOrCreate(['name' => 'oficinaauto.service_order.update', 'guard_name' => 'web']);
    $this->user = User::factory()->create(['business_id' => $this->biz]);
    $this->user->givePermissionTo('oficinaauto.service_order.update');
});

function entregaFotoEntrouEmExecucao(int $biz, int $os, int $stageId, \DateTimeInterface $quando): void
{
    DB::table('sale_stage_history')->insert([
        'business_id' => $biz, 'transaction_id' => $os, 'action_id' => null, 'from_stage_id' => null,
        'to_stage_id' => $stageId, 'user_id' => null, 'payload_snapshot' => json_encode(['teste' => 'entrega-foto']),
        'executed_at' => $quando,
    ]);
}

function entregaFotoAnexo(int $biz, int $os, string $mime, \DateTimeInterface $quando): void
{
    DB::table('arquivos')->insert([
        'business_id' => $biz, 'arquivable_type' => ServiceOrder::class, 'arquivable_id' => $os,
        'disk' => 'arquivos', 'storage_path' => 'tests/entrega-foto-' . uniqid() . '.bin',
        'original_name' => 'foto', 'mime_type' => $mime, 'size_bytes' => 68, 'md5' => md5(uniqid('', true)),
        'bucket' => 'active', 'created_at' => $quando, 'updated_at' => $quando,
    ]);
}

/** Executa pela web (mesmo método da rota `fsm/execute`), sem o middleware de sessão da loja. */
function entregaFotoWeb(object $t, array $dados = ['action_key' => 'entregar'])
{
    $t->actingAs($t->user);
    $os = ServiceOrder::withoutGlobalScopes()->findOrFail($t->os);

    return app()->call([app(ServiceOrderFsmActionController::class), 'execute'], [
        'request' => Request::create('/x', 'POST', $dados),
        'order' => $os,
    ]);
}

function entregaFotoEtapaAtual(object $t): int
{
    return (int) DB::table('service_orders')->where('id', $t->os)->value('current_stage_id');
}

it('UC-OSH-12 · entregar sem foto do serviço é recusado (422): foto do diagnóstico e PDF não contam', function () {
    $gate = app(StageGateEvaluator::class)->evaluate(ServiceOrder::withoutGlobalScopes()->findOrFail($this->os), 'oficina_mecanica_os', 'entregar');
    expect(collect($gate['requirements'])->pluck('key')->all())->toBe(['servico_foto'])
        ->and($gate['satisfied'])->toBeFalse();

    $r = entregaFotoWeb($this);
    expect($r->getStatusCode())->toBe(422)
        ->and($r->getData(true)['gate']['blocking_unmet'])->toBe(1)
        ->and($r->getData(true)['can_override'])->toBeFalse();
    expect(entregaFotoEtapaAtual($this))->toBe(($this->etapa)($this->biz, 'pronto_retirada'));
});

it('UC-OSH-13 · com foto enviada depois de entrar em execução, a OS é entregue', function () {
    entregaFotoAnexo($this->biz, $this->os, 'image/png', now()->subMinutes(10));

    $r = entregaFotoWeb($this);
    expect($r->getStatusCode())->toBe(200)
        ->and(entregaFotoEtapaAtual($this))->toBe(($this->etapa)($this->biz, 'entregue'));
    $trilha = DB::table('sale_stage_history')->where('business_id', $this->biz)->where('transaction_id', $this->os)
        ->where('to_stage_id', ($this->etapa)($this->biz, 'entregue'))->value('payload_snapshot');
    expect(json_decode((string) $trilha, true))->not->toHaveKey('gate_override');
});

it('UC-OSH-14 · gerente libera a entrega sem foto e a liberação fica na trilha', function () {
    $this->user->assignRole("gerente#{$this->biz}");

    // Sem pedir override, o gerente também é barrado (e vê que pode liberar).
    $r = entregaFotoWeb($this);
    expect($r->getStatusCode())->toBe(422)->and($r->getData(true)['can_override'])->toBeTrue();

    $r = entregaFotoWeb($this, ['action_key' => 'entregar', 'override' => true]);
    expect($r->getStatusCode())->toBe(200)
        ->and(entregaFotoEtapaAtual($this))->toBe(($this->etapa)($this->biz, 'entregue'));
    $trilha = json_decode((string) DB::table('sale_stage_history')->where('business_id', $this->biz)
        ->where('transaction_id', $this->os)->where('to_stage_id', ($this->etapa)($this->biz, 'entregue'))
        ->value('payload_snapshot'), true);
    expect($trilha['gate_override'] ?? null)->toBeTrue()
        ->and($trilha['gate_unmet'] ?? null)->toBe(['servico_foto'])
        ->and((int) DB::table('sale_stage_history')->where('business_id', $this->biz)->where('transaction_id', $this->os)
            ->where('to_stage_id', ($this->etapa)($this->biz, 'entregue'))->value('user_id'))->toBe((int) $this->user->id);
});

it('UC-OSH-15 · foto de outra empresa apontando para a OS não conta [T0]', function () {
    // Tenant 99: uma imagem recente no mesmo morph/id da OS do 98.
    entregaFotoAnexo($this->outra, $this->os, 'image/png', now()->subMinutes(5));

    // Sem sessão, como o app chama: o escopo global do Arquivo não filtra nada.
    session()->forget(['user.business_id', 'business.id']);
    $gate = app(StageGateEvaluator::class)->evaluate(ServiceOrder::withoutGlobalScopes()->findOrFail($this->os), 'oficina_mecanica_os', 'entregar');
    expect($gate['satisfied'])->toBeFalse();

    // Controle positivo: a mesma foto, no tenant 98, libera.
    entregaFotoAnexo($this->biz, $this->os, 'image/png', now()->subMinutes(5));
    $gate = app(StageGateEvaluator::class)->evaluate(ServiceOrder::withoutGlobalScopes()->findOrFail($this->os), 'oficina_mecanica_os', 'entregar');
    expect($gate['satisfied'])->toBeTrue();
});

it('UC-OSH-16 · pelo app a regra é a mesma: bloqueado sem foto, sem override; com foto entrega', function () {
    session()->forget(['user.business_id', 'business.id']);
    $app = app(AcoesOs::class);

    $acao = collect($app->acoes($this->user, $this->biz, $this->os))->firstWhere('chave', 'entregar');
    expect($acao['bloqueio'] ?? '')->toContain('Foto do serviço feito');

    $r = $app->executar($this->user, $this->biz, $this->os, 'entregar');
    expect($r['resultado'])->toBe('bloqueado')
        ->and(entregaFotoEtapaAtual($this))->toBe(($this->etapa)($this->biz, 'pronto_retirada'));

    // Outra empresa não alcança a OS.
    expect($app->executar($this->user, $this->outra, $this->os, 'entregar')['resultado'])->toBe('nao_encontrado');

    entregaFotoAnexo($this->biz, $this->os, 'image/jpeg', now()->subMinutes(10));
    expect($app->executar($this->user, $this->biz, $this->os, 'entregar')['resultado'])->toBe('ok')
        ->and(entregaFotoEtapaAtual($this))->toBe(($this->etapa)($this->biz, 'entregue'));
});
