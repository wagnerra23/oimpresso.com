<?php

declare(strict_types=1);
// Cobre as transições do processo "OS e produção por item" (fluxos-processos.jsx, id producao) — ADR 0436, PR 4.

use Illuminate\Foundation\Testing\DatabaseTransactions;
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
 * Fonte: protótipo aprovado pelo [W] em 16/10/2026 (#9517) — ficha da OS (Pausar com motivo,
 * Reprovar com etapa de origem → Retrabalho → Refazer, Cancelar item) e as transições do processo.
 * Tenant 98 × 99 (ADR 0358). ⚠️ SKIP em SQLite: leia assertions (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS.');
    }
    if (! Schema::hasTable('pcp_ordens')) {
        $this->markTestSkipped('pcp_ordens ausente — migrations do Modules/Producao não rodaram.');
    }
    $this->B98 = $this->seededTenant();
    $this->b98 = (int) $this->B98->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    app(ProcessoDaOrdem::class)->garantir($this->b98);
    $this->pcp = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $this->pcp->assignRole('producao.liberar#'.$this->b98, 'producao.qualidade#'.$this->b98, 'vendas.gerente#'.$this->b98);
    $this->op1 = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $this->op2 = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    // SUPERADMIN: fixture da empresa 98; o escopo é provado nos asserts.
    $this->posto = Posto::withoutGlobalScopes()->create(['business_id' => $this->b98, 'codigo' => 'ACAB-'.uniqid(), 'nome' => 'Acabamento',
        'tipo_custo' => 'pessoa', 'custo_hora' => '30.00']);
    $mot = fn ($tipo, $nome) => DB::table('pcp_motivos')->insertGetId(['business_id' => $this->b98, 'posto_id' => $this->posto->id,
        'tipo' => $tipo, 'nome' => $nome, 'ordem' => 0, 'ativo' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $this->motPausa = $mot('pausa', 'Falta material');
    $this->motRefugo = $mot('refugo', 'Cor fora do padrão');

    // OS por item com 3 etapas: bloco 1 (op1), bloco 2 em duas trilhas (op1 e op2).
    $this->ordem = Ordem::withoutGlobalScopes()->create(['business_id' => $this->b98, 'transaction_id' => 990001,
        'transaction_sell_line_id' => random_int(1000000, 9999999), 'product_id' => 1, 'quantidade' => '1.0000',
        'current_stage_id' => app(ProcessoDaOrdem::class)->etapaInicial($this->b98)->id]);
    $et = fn ($bloco, $trilha, $nome, $user) => Programacao::withoutGlobalScopes()->create(['business_id' => $this->b98,
        'os_tipo' => Ordem::OS_TIPO, 'os_id' => $this->ordem->id, 'posto_id' => $this->posto->id, 'bloco' => $bloco, 'trilha' => $trilha,
        'nome' => $nome, 'user_id' => $user->id, 'inicio_previsto' => now()->addMinutes($bloco), 'fim_previsto' => now()->addHour(),
        'status' => 'pendente', 'created_by' => $this->pcp->id]);
    $this->e1 = $et(1, 0, 'Impressão', $this->op1);
    $this->e2a = $et(2, 1, 'Laminação', $this->op1);
    $this->e2b = $et(2, 2, 'Recorte', $this->op2);
});

function sitDe($teste): string
{
    return $teste->ordem->fresh()->currentStage->key;
}

function sitIniciar($teste, Programacao $e, $user)
{
    return app(ApontamentoOperadorService::class)->iniciar($teste->b98, $user->id, $e->id, 'i-'.uniqid(), now());
}

it('OS ainda não liberada não aponta (409 os_nao_liberada) e Liberar põe Na fila', function () {
    expect(fn () => sitIniciar($this, $this->e1, $this->op1))
        ->toThrow(fn (ApontamentoRecusado $e) => expect($e->codigo)->toBe(ApontamentoRecusado::OS_NAO_LIBERADA));

    app(SituacaoDaOrdem::class)->liberar($this->b98, $this->ordem->id, $this->pcp);
    expect(sitDe($this))->toBe('na_fila');
});

it('iniciar a 1ª etapa leva a Em execução; a do bloco seguinte espera o bloco anterior', function () {
    app(SituacaoDaOrdem::class)->liberar($this->b98, $this->ordem->id, $this->pcp);
    sitIniciar($this, $this->e1, $this->op1);
    expect(sitDe($this))->toBe('em_execucao');

    expect(fn () => sitIniciar($this, $this->e2b, $this->op2))
        ->toThrow(ApontamentoRecusado::class, 'Falta terminar a etapa anterior: Impressão.');
});

it('Pausar com motivo leva a Pausada; iniciar de novo é Retomar', function () {
    app(SituacaoDaOrdem::class)->liberar($this->b98, $this->ordem->id, $this->pcp);
    $ap = sitIniciar($this, $this->e1, $this->op1);
    app(ApontamentoOperadorService::class)->pausar($this->b98, $this->op1->id, $ap->id, $this->motPausa, 'p-'.uniqid(), now());
    expect(sitDe($this))->toBe('pausada');

    sitIniciar($this, $this->e1, $this->op1);
    expect(sitDe($this))->toBe('em_execucao');
});

it('com duas trilhas rodando, pausar uma não pausa a OS', function () {
    app(SituacaoDaOrdem::class)->liberar($this->b98, $this->ordem->id, $this->pcp);
    $ap = sitIniciar($this, $this->e1, $this->op1);
    app(ApontamentoOperadorService::class)->concluir($this->b98, $this->op1->id, $ap->id, 'c-'.uniqid(), now());
    $a = sitIniciar($this, $this->e2a, $this->op1);
    sitIniciar($this, $this->e2b, $this->op2);

    app(ApontamentoOperadorService::class)->pausar($this->b98, $this->op1->id, $a->id, $this->motPausa, 'p-'.uniqid(), now());
    expect(sitDe($this))->toBe('em_execucao');
});

it('Reprovar no controle: Retrabalho, etapa de origem volta como etapa nova, nada aponta até Refazer', function () {
    app(SituacaoDaOrdem::class)->liberar($this->b98, $this->ordem->id, $this->pcp);
    $ap = sitIniciar($this, $this->e1, $this->op1);
    app(ApontamentoOperadorService::class)->concluir($this->b98, $this->op1->id, $ap->id, 'c-'.uniqid(), now());

    app(SituacaoDaOrdem::class)->reprovar($this->b98, $this->ordem->id, $this->e1->id, $this->motRefugo, $this->pcp);
    expect(sitDe($this))->toBe('retrabalho');
    $nova = Programacao::withoutGlobalScopes()->where('os_tipo', Ordem::OS_TIPO)->where('os_id', $this->ordem->id)->where('nome', 'Impressão (retrabalho)')->first();
    expect($nova)->not->toBeNull();
    expect($nova->status)->toBe('pendente');
    expect(fn () => sitIniciar($this, $nova, $this->op1))->toThrow(ApontamentoRecusado::class, 'em retrabalho');

    app(SituacaoDaOrdem::class)->refazer($this->b98, $this->ordem->id, $this->pcp);
    expect(sitDe($this))->toBe('em_execucao');
});

it('Reprovar exige motivo de refugo do posto e etapa de origem já feita', function () {
    app(SituacaoDaOrdem::class)->liberar($this->b98, $this->ordem->id, $this->pcp);
    sitIniciar($this, $this->e1, $this->op1);

    expect(fn () => app(SituacaoDaOrdem::class)->reprovar($this->b98, $this->ordem->id, $this->e1->id, $this->motRefugo, $this->pcp))
        ->toThrow(ApontamentoRecusado::class, 'etapa já feita');
    expect(sitDe($this))->toBe('em_execucao');
});

it('Cancelar item antes de liberar cancela as etapas programadas', function () {
    app(SituacaoDaOrdem::class)->cancelarItem($this->b98, $this->ordem->id, $this->pcp);

    expect(sitDe($this))->toBe('cancelada');
    expect(Programacao::withoutGlobalScopes()->where('os_tipo', Ordem::OS_TIPO)->where('os_id', $this->ordem->id)->pluck('status')->unique()->all())
        ->toBe(['cancelada']);
});

it('Tier 0: a 99 não libera a OS da 98', function () {
    expect(fn () => app(SituacaoDaOrdem::class)->liberar($this->b99, $this->ordem->id, $this->pcp))
        ->toThrow(fn (ApontamentoRecusado $e) => expect($e->codigo)->toBe(ApontamentoRecusado::NAO_ENCONTRADO));
    expect(sitDe($this))->toBe('aguardando_liberacao');
});
