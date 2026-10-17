<?php

declare(strict_types=1);
// Cobre "Concluir item" do processo "OS e produção por item" (fluxos-processos.jsx, id producao) e UC-FLX-15 — ADR 0436, PR 5.

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
 * Fonte: protótipo aprovado pelo [W] em 16/10/2026 (#9517) — "Concluir item: última etapa do roteiro
 * feita → congela custo · OS pronta". Custo é VALOR (regra mestre): conferido por dois caminhos, o
 * serviço e a conta à mão no teste. Tenant 98 (ADR 0358). ⚠️ SKIP em SQLite (LC-13).
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
    app(ProcessoDaOrdem::class)->garantir($this->b98);
    $this->pcp = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $this->pcp->assignRole('producao.liberar#'.$this->b98);
    $this->op1 = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $this->op2 = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    // SUPERADMIN: fixture da empresa 98.
    $this->posto = Posto::withoutGlobalScopes()->create(['business_id' => $this->b98, 'codigo' => 'ACAB-'.uniqid(), 'nome' => 'Acabamento',
        'tipo_custo' => 'pessoa', 'custo_hora' => '30.00']);
    $this->ordem = Ordem::withoutGlobalScopes()->create(['business_id' => $this->b98, 'transaction_id' => 990002,
        'transaction_sell_line_id' => random_int(1000000, 9999999), 'product_id' => 1, 'quantidade' => '1.0000',
        'current_stage_id' => app(ProcessoDaOrdem::class)->etapaInicial($this->b98)->id]);
    $et = fn ($bloco, $trilha, $nome, $user) => Programacao::withoutGlobalScopes()->create(['business_id' => $this->b98,
        'os_tipo' => Ordem::OS_TIPO, 'os_id' => $this->ordem->id, 'posto_id' => $this->posto->id, 'bloco' => $bloco, 'trilha' => $trilha,
        'nome' => $nome, 'user_id' => $user->id, 'inicio_previsto' => now()->subHours(5)->addMinutes($bloco), 'fim_previsto' => now(),
        'status' => 'pendente', 'created_by' => $this->pcp->id]);
    $this->e1 = $et(1, 0, 'Impressão', $this->op1);
    $this->e2a = $et(2, 1, 'Laminação', $this->op1);
    $this->e2b = $et(2, 2, 'Recorte', $this->op2);
    app(SituacaoDaOrdem::class)->liberar($this->b98, $this->ordem->id, $this->pcp);
});

/** Aponta a etapa de `de` a `ate` horas atrás. */
function ciAponta($teste, Programacao $e, $user, float $de, float $ate)
{
    $svc = app(ApontamentoOperadorService::class);
    $ap = $svc->iniciar($teste->b98, $user->id, $e->id, 'i-'.uniqid(), now()->subMinutes((int) ($de * 60)));

    return $svc->concluir($teste->b98, $user->id, $ap->id, 'c-'.uniqid(), now()->subMinutes((int) ($ate * 60)));
}

it('a última etapa concluída conclui o item, deixa a OS pronta e congela o custo (conta à mão confere)', function () {
    ciAponta($this, $this->e1, $this->op1, 4, 3);      // 1 h
    ciAponta($this, $this->e2a, $this->op1, 3, 2.5);   // 0,5 h
    expect($this->ordem->fresh()->currentStage->key)->toBe('em_execucao'); // a outra trilha ainda falta
    ciAponta($this, $this->e2b, $this->op2, 3, 1);     // 2 h

    $o = $this->ordem->fresh();
    expect($o->currentStage->key)->toBe('concluida');
    expect($o->pronta_em)->not->toBeNull();
    // Caminho 2 (à mão): 3,5 h de pessoa × custo-hora 30,00 = 105,00.
    expect((string) $o->custo_real)->toBe('105.0000');
    expect($o->custo_congelado_em)->not->toBeNull();
});

it('o custo fica congelado: apontamento lançado depois não muda o valor gravado', function () {
    ciAponta($this, $this->e1, $this->op1, 4, 3);
    ciAponta($this, $this->e2a, $this->op1, 3, 2.5);
    ciAponta($this, $this->e2b, $this->op2, 3, 1);
    $congelado = (string) $this->ordem->fresh()->custo_real;

    DB::table('pcp_apontamentos')->insert(['business_id' => $this->b98, 'chave_idempotencia' => 'x-'.uniqid(), 'tipo' => 'inicio',
        'os_tipo' => Ordem::OS_TIPO, 'os_id' => $this->ordem->id, 'posto_id' => $this->posto->id, 'tipo_custo' => 'pessoa', 'custo_hora' => '30.00',
        'user_id' => $this->op1->id, 'iniciado_por' => $this->op1->id, 'momento' => now()->subMinutes(30), 'origem' => 'manual', 'created_at' => now()]);

    expect((string) $this->ordem->fresh()->custo_real)->toBe($congelado);
});

it('concluir de novo é inócuo: a OS conclui uma vez só', function () {
    ciAponta($this, $this->e1, $this->op1, 4, 3);
    ciAponta($this, $this->e2a, $this->op1, 3, 2.5);
    ciAponta($this, $this->e2b, $this->op2, 3, 1);

    expect(app(SituacaoDaOrdem::class)->concluirItemSeTerminou($this->b98, $this->ordem->id, $this->op1->id))->toBeFalse();
    $conclusoes = DB::table('sale_stage_history')->where('business_id', $this->b98)->where('transaction_id', $this->ordem->id)
        ->where('to_stage_id', $this->ordem->fresh()->current_stage_id)->count();
    expect($conclusoes)->toBe(1);
});
