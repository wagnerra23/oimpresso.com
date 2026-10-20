<?php
// @covers-us US-PCP-030

declare(strict_types=1);
// Cobre UC-PCOPE-01..05 (Postos/Index.casos.md · US-PCP-030 · UC-FLX-08).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Entities\Ordem;
use Modules\Producao\Entities\Posto;
use Modules\Producao\Entities\Programacao;
use Modules\Producao\Exceptions\ApontamentoRecusado;
use Modules\Producao\Services\ApontamentoOperadorService;
use Modules\Producao\Services\ProcessoDaOrdem;
use Modules\Producao\Services\SituacaoDaOrdem;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Fonte: fluxos-modelos.casos.md:164 — "Iniciar etapa · só se: Operador do setor da etapa" — e a
 * aprovação do [W] em 2026-10-20 (cadastro de operadores por posto). Os casos não derivam do código.
 * Tenant 98 × 99 (ADR 0358), NUNCA biz=4. ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS.');
    }
    if (! Schema::hasTable('pcp_posto_operadores') || ! Schema::hasTable('pcp_ordens')) {
        $this->markTestSkipped('pcp_posto_operadores ausente — migrations do Modules/Producao não rodaram.');
    }
    Carbon::setTestNow('2026-10-20 10:00:00');
    $this->B98 = $this->seededTenant();
    $this->b98 = (int) $this->B98->id;
    $this->B99 = $this->seededSupportClientTenant();
    $this->gestor = $this->usuarioComPermissoes(['producao.access', 'producao.etapa.mover'], $this->B98);
    $this->semMover = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $this->ana = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $this->bia = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $this->op99 = $this->usuarioComPermissoes(['producao.access'], $this->B99);
    $this->plotter = opePosto($this->b98, 'Plotter');
    $this->acabamento = opePosto($this->b98, 'Acabamento');
    $this->posto99 = opePosto((int) $this->B99->id, 'Plotter 99');
});

afterEach(fn () => Carbon::setTestNow());

function opePosto(int $biz, string $nome): Posto
{
    // SUPERADMIN: fixture grava em duas empresas; o escopo é provado nos asserts, não aqui.
    return Posto::withoutGlobalScopes()->create(['business_id' => $biz, 'codigo' => 'OPE-'.uniqid(), 'nome' => $nome,
        'tipo_custo' => 'pessoa', 'custo_hora' => '30.00']);
}

function opeComo($teste, $user)
{
    $teste->flushSession();

    return $teste->actingAs($user);
}

function opeEtapa(int $biz, int $posto, ?int $resp, string $osTipo = 'comvis_os', int $osId = 701, string $nome = 'Impressão'): Programacao
{
    // SUPERADMIN: fixture da empresa 98.
    return Programacao::withoutGlobalScopes()->create(['business_id' => $biz, 'os_tipo' => $osTipo, 'os_id' => $osId, 'posto_id' => $posto,
        'bloco' => 1, 'nome' => $nome, 'user_id' => $resp, 'inicio_previsto' => now(), 'fim_previsto' => now()->addHour(),
        'status' => 'pendente', 'created_by' => $resp ?? 1]);
}

function opeOrdemComEtapa($teste): Programacao
{
    app(ProcessoDaOrdem::class)->garantir($teste->b98);
    // SUPERADMIN: fixture da empresa 98.
    $ordem = Ordem::withoutGlobalScopes()->create(['business_id' => $teste->b98, 'transaction_id' => 990030,
        'transaction_sell_line_id' => random_int(1000000, 9999999), 'product_id' => 1, 'quantidade' => '1.0000',
        'current_stage_id' => app(ProcessoDaOrdem::class)->etapaInicial($teste->b98)->id]);
    $teste->ordem = $ordem;

    return opeEtapa($teste->b98, (int) $teste->plotter->id, null, Ordem::OS_TIPO, (int) $ordem->id);
}

it('UC-PCOPE-01 · o gestor define os operadores do posto e a lista mostra só eles; outro posto segue sem cadastro', function () {
    opeComo($this, $this->gestor)->putJson("/producao/postos/{$this->plotter->id}/operadores", ['user_ids' => [$this->ana->id, $this->bia->id]])
        ->assertOk()->assertJsonCount(2, 'operadores');

    $ids = collect(opeComo($this, $this->semMover)->getJson("/producao/postos/{$this->plotter->id}/operadores")->assertOk()->json('operadores'))->pluck('id')->sort()->values()->all();
    expect($ids)->toBe(collect([(int) $this->ana->id, (int) $this->bia->id])->sort()->values()->all());
    expect(opeComo($this, $this->gestor)->getJson("/producao/postos/{$this->acabamento->id}/operadores")->json('operadores'))->toBe([]);

    // Lista vazia limpa o cadastro: o posto volta a aceitar qualquer pessoa.
    opeComo($this, $this->gestor)->putJson("/producao/postos/{$this->plotter->id}/operadores", ['user_ids' => []])->assertOk()->assertJsonCount(0, 'operadores');
    expect(DB::table('pcp_posto_operadores')->where('business_id', $this->b98)->where('posto_id', $this->plotter->id)->count())->toBe(0);
});

it('UC-PCOPE-02 · Liberar: posto com operadores recusa responsável fora do cadastro e aceita um operador', function () {
    $etapa = opeOrdemComEtapa($this);
    $pcp = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $pcp->assignRole('producao.liberar#'.$this->b98);
    DB::table('pcp_posto_operadores')->insert(['business_id' => $this->b98, 'posto_id' => $this->plotter->id, 'user_id' => $this->ana->id,
        'created_at' => now(), 'updated_at' => now()]);

    expect(fn () => app(SituacaoDaOrdem::class)->liberar($this->b98, $this->ordem->id, $pcp, [$etapa->id => $this->bia->id]))
        ->toThrow(fn (ApontamentoRecusado $e) => expect($e->codigo)->toBe(ApontamentoRecusado::RESPONSAVEL_INVALIDO)
            ->and($e->getMessage())->toBe('Responsável não é operador do posto de Impressão.'));
    expect($etapa->fresh()->user_id)->toBeNull();
    expect($this->ordem->fresh()->currentStage->key)->toBe('aguardando_liberacao');

    app(SituacaoDaOrdem::class)->liberar($this->b98, $this->ordem->id, $pcp, [$etapa->id => $this->ana->id]);
    expect((int) $etapa->fresh()->user_id)->toBe((int) $this->ana->id);
    expect($this->ordem->fresh()->currentStage->key)->toBe('na_fila');
});

it('UC-PCOPE-03 · posto SEM operadores cadastrados segue como antes: qualquer pessoa da empresa libera e começa', function () {
    $etapa = opeOrdemComEtapa($this);
    $pcp = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $pcp->assignRole('producao.liberar#'.$this->b98);
    // O OUTRO posto tem cadastro: não pode vazar para o Plotter.
    DB::table('pcp_posto_operadores')->insert(['business_id' => $this->b98, 'posto_id' => $this->acabamento->id, 'user_id' => $this->ana->id,
        'created_at' => now(), 'updated_at' => now()]);

    app(SituacaoDaOrdem::class)->liberar($this->b98, $this->ordem->id, $pcp, [$etapa->id => $this->bia->id]);
    expect((int) $etapa->fresh()->user_id)->toBe((int) $this->bia->id);

    $comvis = opeEtapa($this->b98, (int) $this->plotter->id, (int) $this->bia->id, osId: 702);
    $ap = app(ApontamentoOperadorService::class)->iniciar($this->b98, (int) $this->bia->id, $comvis->id, 'ope03-'.uniqid(), now());
    expect((int) $ap->programacao_id)->toBe((int) $comvis->id);
    expect($comvis->fresh()->status)->toBe('iniciada');
});

it('UC-PCOPE-04 · começar a etapa: responsável que não é operador do posto é recusado, nada gravado; o operador começa', function () {
    // Responsável escolhido ANTES do cadastro do posto: o início confere de novo.
    $etapa = opeEtapa($this->b98, (int) $this->plotter->id, (int) $this->bia->id);
    DB::table('pcp_posto_operadores')->insert(['business_id' => $this->b98, 'posto_id' => $this->plotter->id, 'user_id' => $this->ana->id,
        'created_at' => now(), 'updated_at' => now()]);

    expect(fn () => app(ApontamentoOperadorService::class)->iniciar($this->b98, (int) $this->bia->id, $etapa->id, 'ope04-'.uniqid(), now()))
        ->toThrow(fn (ApontamentoRecusado $e) => expect($e->codigo)->toBe(ApontamentoRecusado::ETAPA_ALHEIA)
            ->and($e->getMessage())->toBe('Você não está cadastrado como operador deste posto.'));
    expect($etapa->fresh()->status)->toBe('pendente');
    expect(DB::table('pcp_apontamentos')->where('business_id', $this->b98)->where('programacao_id', $etapa->id)->count())->toBe(0);

    $etapa->forceFill(['user_id' => $this->ana->id])->save();
    app(ApontamentoOperadorService::class)->iniciar($this->b98, (int) $this->ana->id, $etapa->id, 'ope04b-'.uniqid(), now());
    expect($etapa->fresh()->status)->toBe('iniciada');
});

it('UC-PCOPE-05 · Tier 0 e permissão: operador de outra empresa, posto de outra empresa e quem não é gestor são recusados', function () {
    opeComo($this, $this->gestor)->putJson("/producao/postos/{$this->plotter->id}/operadores", ['user_ids' => [$this->ana->id, $this->op99->id]])
        ->assertStatus(422)->assertJsonPath('codigo', ApontamentoRecusado::RESPONSAVEL_INVALIDO);
    opeComo($this, $this->gestor)->putJson("/producao/postos/{$this->posto99->id}/operadores", ['user_ids' => [$this->ana->id]])->assertNotFound();
    opeComo($this, $this->gestor)->getJson("/producao/postos/{$this->posto99->id}/operadores")->assertNotFound();
    opeComo($this, $this->semMover)->putJson("/producao/postos/{$this->plotter->id}/operadores", ['user_ids' => [$this->ana->id]])->assertForbidden();

    expect(DB::table('pcp_posto_operadores')->whereIn('posto_id', [$this->plotter->id, $this->posto99->id])->count())->toBe(0);
});
