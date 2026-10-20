<?php

declare(strict_types=1);
// Cobre o alocador: quem e quando começa cada etapa (UC-FLX-10 · US-PCP-029).

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Entities\Evento;
use Modules\Producao\Entities\Ordem;
use Modules\Producao\Entities\Posto;
use Modules\Producao\Entities\Programacao;
use Modules\Producao\Services\AlocacaoDaEtapa;
use Modules\Producao\Services\ProcessoDaOrdem;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Fonte: UC-FLX-10 do catálogo de fluxos (`CatalogoModelos`: etapa Liberada → "Alocador escolhe quem
 * e quando") — *"escolhe operador e horário para não parar máquina nem faltar material"*. Tenant 98 × 99
 * (ADR 0358). ⚠️ SKIP em SQLite: leia assertions (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS.');
    }
    if (! Schema::hasTable('pcp_ordens')) {
        $this->markTestSkipped('pcp_ordens ausente — migrations do Modules/Producao não rodaram.');
    }
    $this->partialMock(ModuleUtil::class, fn ($m) => $m->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($b, $perm) => $perm === 'producao_module'));
    $this->B98 = $this->seededTenant();
    $this->b98 = (int) $this->B98->id;
    $this->B99 = $this->seededSupportClientTenant();
    $this->b99 = (int) $this->B99->id;
    app(ProcessoDaOrdem::class)->garantir($this->b98);
    $this->gestor = $this->usuarioComPermissoes(['producao.access', 'producao.etapa.mover'], $this->B98);
    $this->op = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $this->op2 = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $this->semAcesso = $this->usuarioComPermissoes([], $this->B98);
    $this->op99 = $this->usuarioComPermissoes(['producao.access'], $this->B99);
    // SUPERADMIN: fixtures das empresas 98 e 99.
    $posto = fn (int $biz, string $nome) => Posto::withoutGlobalScopes()->create(['business_id' => $biz, 'codigo' => 'P-'.uniqid(),
        'nome' => $nome, 'tipo_custo' => 'maquina', 'custo_hora' => '50.00']);
    $this->posto = $posto($this->b98, 'Plotter');
    $this->posto99 = $posto($this->b99, 'Plotter 99');
    $this->ordem = Ordem::withoutGlobalScopes()->create(['business_id' => $this->b98, 'transaction_id' => 990010,
        'transaction_sell_line_id' => random_int(1000000, 9999999), 'product_id' => 1, 'quantidade' => '1.0000',
        'current_stage_id' => app(ProcessoDaOrdem::class)->etapaInicial($this->b98)->id]);
    $this->et = fn (int $biz, int $postoId, string $nome, string $inicio, string $status = 'pendente', ?int $user = null) => Programacao::withoutGlobalScopes()->create([
        'business_id' => $biz, 'os_tipo' => Ordem::OS_TIPO, 'os_id' => $this->ordem->id, 'posto_id' => $postoId, 'bloco' => 1, 'nome' => $nome,
        'user_id' => $user, 'inicio_previsto' => $inicio, 'fim_previsto' => date('Y-m-d H:i:s', strtotime($inicio) + 5400),
        'status' => $status, 'created_by' => $this->gestor->id]);
    $this->etapa = ($this->et)($this->b98, $this->posto->id, 'Impressão', '2026-11-03 08:00:00', 'pendente', $this->op->id);
});

it('UC-PCALO-01 · o alocador troca o responsável e o início; a duração da etapa se mantém', function () {
    $this->actingAs($this->gestor)->putJson("/producao/etapas/{$this->etapa->id}/alocacao",
        ['user_id' => $this->op2->id, 'inicio_previsto' => '2026-11-03 14:00:00'])
        ->assertOk()->assertJson(['user_id' => $this->op2->id, 'inicio_previsto' => '2026-11-03 14:00:00', 'fim_previsto' => '2026-11-03 15:30:00']);

    $e = $this->etapa->fresh();
    expect((int) $e->user_id)->toBe((int) $this->op2->id)
        ->and($e->inicio_previsto->toDateTimeString())->toBe('2026-11-03 14:00:00')
        ->and($e->fim_previsto->toDateTimeString())->toBe('2026-11-03 15:30:00');
    // A situação da OS não muda: alocar não é ação do FSM.
    expect($this->ordem->fresh()->currentStage->key)->toBe('aguardando_liberacao');
});

it('UC-PCALO-02 · quem mudou e quando fica no histórico, com o antes e o depois', function () {
    $this->actingAs($this->gestor)->putJson("/producao/etapas/{$this->etapa->id}/alocacao", ['inicio_previsto' => '2026-11-04 08:00:00'])->assertOk();

    $ev = Evento::withoutGlobalScopes()->where('business_id', $this->b98)->where('tipo', AlocacaoDaEtapa::EV_ALOCADA)->latest('id')->first();
    expect($ev)->not->toBeNull()
        ->and((int) $ev->user_id)->toBe((int) $this->gestor->id)
        ->and($ev->created_at)->not->toBeNull()
        ->and($ev->dados['programacao_id'])->toBe((int) $this->etapa->id)
        ->and($ev->dados['antes'])->toBe(['user_id' => (int) $this->op->id, 'inicio_previsto' => '2026-11-03 08:00:00'])
        ->and($ev->dados['depois'])->toBe(['user_id' => (int) $this->op->id, 'inicio_previsto' => '2026-11-04 08:00:00']);
    expect((int) $this->etapa->fresh()->user_id)->toBe((int) $this->op->id);
});

it('UC-PCALO-03 · etapa iniciada ou concluída não se realoca, e nada muda', function () {
    $eventos = fn () => Evento::withoutGlobalScopes()->where('business_id', $this->b98)->where('tipo', AlocacaoDaEtapa::EV_ALOCADA)->count();
    $antes = $eventos();
    foreach (['iniciada', 'concluida'] as $status) {
        $e = ($this->et)($this->b98, $this->posto->id, 'Laminação', '2026-11-03 10:00:00', $status, $this->op->id);
        $this->actingAs($this->gestor)->putJson("/producao/etapas/{$e->id}/alocacao", ['user_id' => $this->op2->id])
            ->assertStatus(422)->assertJson(['codigo' => 'etapa_encerrada']);
        expect((int) $e->fresh()->user_id)->toBe((int) $this->op->id);
    }
    expect($eventos())->toBe($antes);
});

it('UC-PCALO-04 · responsável de outra empresa ou sem acesso à Produção é recusado', function () {
    foreach ([$this->op99->id, $this->semAcesso->id] as $uid) {
        $this->actingAs($this->gestor)->putJson("/producao/etapas/{$this->etapa->id}/alocacao", ['user_id' => $uid])
            ->assertStatus(422)->assertJson(['codigo' => 'responsavel_invalido']);
    }
    expect((int) $this->etapa->fresh()->user_id)->toBe((int) $this->op->id);
});

it('UC-PCALO-05 · operador não aloca (403) e etapa ou posto de outra empresa é 404', function () {
    $this->actingAs($this->op)->putJson("/producao/etapas/{$this->etapa->id}/alocacao", ['user_id' => $this->op->id])->assertForbidden();
    $this->actingAs($this->op)->getJson("/producao/postos/{$this->posto->id}/fila")->assertForbidden();
    expect((int) $this->etapa->fresh()->user_id)->toBe((int) $this->op->id);

    $alheia = ($this->et)($this->b99, $this->posto99->id, 'Alheia', '2026-11-03 08:00:00', 'pendente', $this->op99->id);
    $this->actingAs($this->gestor)->putJson("/producao/etapas/{$alheia->id}/alocacao", ['inicio_previsto' => '2026-11-05 08:00:00'])->assertNotFound();
    expect($alheia->fresh()->inicio_previsto->toDateTimeString())->toBe('2026-11-03 08:00:00');
    $this->actingAs($this->gestor)->getJson("/producao/postos/{$this->posto99->id}/fila")->assertNotFound();
});

it('UC-PCALO-06 · a fila do posto vem pela ordem do início previsto, só com etapas abertas', function () {
    $antes = ($this->et)($this->b98, $this->posto->id, 'Corte', '2026-11-02 08:00:00', 'iniciada', $this->op2->id);
    ($this->et)($this->b98, $this->posto->id, 'Feita', '2026-11-01 08:00:00', 'concluida', $this->op2->id);
    $depois = ($this->et)($this->b98, $this->posto->id, 'Acabamento', '2026-11-05 08:00:00');

    $r = $this->actingAs($this->gestor)->getJson("/producao/postos/{$this->posto->id}/fila")->assertOk()->json('etapas');

    expect(array_column($r, 'id'))->toBe([(int) $antes->id, (int) $this->etapa->id, (int) $depois->id]);
    expect($r[1]['user_id'])->toBe((int) $this->op->id)->and($r[1]['responsavel'])->not->toBeNull()
        ->and($r[2]['user_id'])->toBeNull()->and($r[1]['inicio_previsto'])->toBe('2026-11-03 08:00:00');
});
