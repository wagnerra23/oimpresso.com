<?php

declare(strict_types=1);
// Cobre UC-PCOSR-01..05 (Producao/Apontamento/Index.casos.md) — rotas das ações de tela da OS por item.

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Entities\Ordem;
use Modules\Producao\Entities\Posto;
use Modules\Producao\Entities\Programacao;
use Modules\Producao\Services\ApontamentoOperadorService;
use Modules\Producao\Services\ProcessoDaOrdem;
use Modules\Producao\Services\SituacaoDaOrdem;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Auditoria Producao lote 2.2: Liberar, Refazer e Cancelar item ganham rota (JSON). Os papéis são os do
 * motor (ProcessoDaOrdem::ACOES): Liberar e Refazer = producao.liberar; Cancelar item = vendas.gerente.
 * Tenant 98 × 99 (ADR 0358). ⚠️ SKIP em SQLite: leia assertions (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS.');
    }
    if (! Schema::hasTable('pcp_ordens')) {
        $this->markTestSkipped('pcp_ordens ausente — migrations do Modules/Producao não rodaram.');
    }
    $this->pacote = true;
    $this->partialMock(ModuleUtil::class, fn ($m) => $m->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($b, $perm) => $this->pacote && $perm === 'producao_module'));
    $this->B98 = $this->seededTenant();
    $this->b98 = (int) $this->B98->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    app(ProcessoDaOrdem::class)->garantir($this->b98);
    $this->pcp = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $this->pcp->assignRole('producao.liberar#'.$this->b98, 'producao.qualidade#'.$this->b98);
    $this->gerente = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $this->gerente->assignRole('vendas.gerente#'.$this->b98);
    $this->op = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    // SUPERADMIN: fixture da empresa 98; o escopo é provado nos asserts.
    $this->posto = Posto::withoutGlobalScopes()->create(['business_id' => $this->b98, 'codigo' => 'ACAB-'.uniqid(), 'nome' => 'Acabamento',
        'tipo_custo' => 'pessoa', 'custo_hora' => '30.00']);
    $this->motRefugo = DB::table('pcp_motivos')->insertGetId(['business_id' => $this->b98, 'posto_id' => $this->posto->id,
        'tipo' => 'refugo', 'nome' => 'Cor fora do padrão', 'ordem' => 0, 'ativo' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $this->ordem = Ordem::withoutGlobalScopes()->create(['business_id' => $this->b98, 'transaction_id' => 990011,
        'transaction_sell_line_id' => random_int(1000000, 9999999), 'product_id' => 1, 'quantidade' => '1.0000',
        'current_stage_id' => app(ProcessoDaOrdem::class)->etapaInicial($this->b98)->id]);
    // Duas etapas: concluir a 1ª não conclui a OS, e o Reprovar tem uma etapa feita de origem.
    $et = fn ($bloco, $nome) => Programacao::withoutGlobalScopes()->create(['business_id' => $this->b98, 'os_tipo' => Ordem::OS_TIPO,
        'os_id' => $this->ordem->id, 'posto_id' => $this->posto->id, 'bloco' => $bloco, 'trilha' => 0, 'nome' => $nome, 'user_id' => null,
        'inicio_previsto' => now()->addMinutes($bloco), 'fim_previsto' => now()->addHour(), 'status' => 'pendente', 'created_by' => $this->pcp->id]);
    $this->e1 = $et(1, 'Impressão');
    $this->e2 = $et(2, 'Recorte');
    $this->todos = [$this->e1->id => $this->op->id, $this->e2->id => $this->op->id];
    $this->sit = fn () => $this->ordem->fresh()->currentStage->key;
});

it('UC-PCOSR-01 · Liberar pela rota: sem o papel 403; sem responsável 422; com os responsáveis, Na fila', function () {
    $url = "/producao/ordens/{$this->ordem->id}/liberar";
    $this->actingAs($this->op)->postJson($url, ['responsaveis' => $this->todos])->assertForbidden();
    expect(($this->sit)())->toBe('aguardando_liberacao');

    $this->actingAs($this->pcp)->postJson($url, ['responsaveis' => [$this->e1->id => $this->op->id]])
        ->assertStatus(422)->assertJsonPath('erro', 'responsavel_invalido');
    expect(($this->sit)())->toBe('aguardando_liberacao')->and($this->e1->fresh()->user_id)->toBeNull();

    $this->actingAs($this->pcp)->postJson($url, ['responsaveis' => $this->todos])->assertOk()
        ->assertJson(['id' => $this->ordem->id, 'situacao' => 'na_fila']);
    expect(($this->sit)())->toBe('na_fila')->and((int) $this->e2->fresh()->user_id)->toBe((int) $this->op->id);

    // De novo: a ação não vale mais na situação atual.
    $this->actingAs($this->pcp)->postJson($url)->assertStatus(409)->assertJsonPath('erro', 'acao_invalida');
});

it('UC-PCOSR-02 · Refazer pela rota: fora de Retrabalho 409; sem o papel 403; em Retrabalho volta a Em execução', function () {
    $url = "/producao/ordens/{$this->ordem->id}/refazer";
    $this->actingAs($this->pcp)->postJson($url)->assertStatus(409);

    app(SituacaoDaOrdem::class)->liberar($this->b98, $this->ordem->id, $this->pcp, $this->todos);
    $svc = app(ApontamentoOperadorService::class);
    $ap = $svc->iniciar($this->b98, $this->op->id, $this->e1->id, 'i-'.uniqid(), now());
    $svc->concluir($this->b98, $this->op->id, $ap->id, 'c-'.uniqid(), now());
    app(SituacaoDaOrdem::class)->reprovar($this->b98, $this->ordem->id, $this->e1->id, $this->motRefugo, $this->pcp);
    expect(($this->sit)())->toBe('retrabalho');

    $this->actingAs($this->op)->postJson($url)->assertForbidden();
    expect(($this->sit)())->toBe('retrabalho');
    $this->actingAs($this->pcp)->postJson($url)->assertOk()->assertJson(['situacao' => 'em_execucao']);
    expect(($this->sit)())->toBe('em_execucao');
});

it('UC-PCOSR-03 · Cancelar item pela rota: só o gerente; depois de cancelada vira 409', function () {
    $url = "/producao/ordens/{$this->ordem->id}/cancelar";
    $this->actingAs($this->pcp)->postJson($url)->assertForbidden();
    expect(($this->sit)())->toBe('aguardando_liberacao');

    $this->actingAs($this->gerente)->postJson($url)->assertOk()->assertJson(['situacao' => 'cancelada']);
    expect($this->e1->fresh()->status)->toBe('cancelada')->and($this->e2->fresh()->status)->toBe('cancelada');
    $this->actingAs($this->gerente)->postJson($url)->assertStatus(409);
});

it('UC-PCOSR-04 · Tier 0: OS de outra empresa é 404 nas três rotas, e nada muda', function () {
    // SUPERADMIN: fixture da empresa 99.
    $os99 = Ordem::withoutGlobalScopes()->create(['business_id' => $this->b99, 'transaction_id' => 990012,
        'transaction_sell_line_id' => random_int(1000000, 9999999), 'product_id' => 1, 'quantidade' => '1.0000',
        'current_stage_id' => app(ProcessoDaOrdem::class)->etapaInicial($this->b99)->id]);
    foreach (['liberar', 'refazer', 'cancelar'] as $acao) {
        $this->actingAs($this->gerente)->postJson("/producao/ordens/{$os99->id}/{$acao}")->assertNotFound();
    }
    expect($os99->fresh('currentStage')->currentStage->key)->toBe('aguardando_liberacao');
});

it('UC-PCOSR-05 · sem o módulo no pacote, 403 nas três rotas', function () {
    $this->pacote = false;
    foreach (['liberar', 'refazer', 'cancelar'] as $acao) {
        $this->actingAs($this->gerente)->postJson("/producao/ordens/{$this->ordem->id}/{$acao}")->assertForbidden();
    }
    expect(($this->sit)())->toBe('aguardando_liberacao');
});
