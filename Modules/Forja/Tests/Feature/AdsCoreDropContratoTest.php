<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Forja\Services\ProjectService;

/**
 * Contrato do E5 da deprecação do Modules/ADS (ADR 0363).
 *
 * O DROP do núcleo dual-brain é irreversível e vizinho de 6 tabelas que FICARAM
 * porque têm consumidor vivo nesta casa. Este teste defende os dois lados:
 *
 *   1. o que morreu, morreu       — `mcp_dual_brain_decisions` & cia não voltam
 *   2. o que sobreviveu, sobrevive — as 6 continuam de pé (controle negativo)
 *   3. a tela sobrevivente não consulta a tabela morta
 *
 * O (2) é o que morde de verdade: se alguém acrescentar uma das 6 à lista do
 * `up()` da migration por engano, este teste fica vermelho ANTES de a Forja virar
 * 500 em produção. Foi assim que `mcp_tool_executions` e `mcp_user_module_access`
 * quase foram dropadas — o plano as listava como "DROP (0 linhas)" porque o
 * consumidor de então (TeamMcp) morreu, sem ver que a Forja tinha herdado ambas.
 *
 * @see memory/decisions/0363-governance-incorpora-ads-nucleo-sem-receptor.md
 * @see memory/requisitos/ADS/DEPRECATION-PLAN.md §Roadmap E5
 * @see database/migrations/2026_07_31_235000_drop_ads_dual_brain_core_tables.php
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: as tabelas mcp_* vivem no schema MySQL UltimatePOS (ADR 0101)');
    }
});

const ADS_DROP_BIZ_WAGNER = 1;                    // biz=1 (Wagner) — NUNCA biz=4 (ADR 0101)
const ADS_DROP_CODIGO_TESTE = 'ADSDROP-TEST';

/** As 5 que o E5 dropou. */
dataset('tabelas_dropadas', [
    'mcp_dual_brain_decisions',
    'mcp_confidence_scores',
    'mcp_decision_patterns',
    'mcp_decision_thresholds',
    'mcp_file_locks',
]);

/** As 6 que FICARAM — cada uma com consumidor vivo fora do ADS. */
dataset('tabelas_preservadas', [
    'mcp_decision_links',     // Forja/Services/DecisionLinksService
    'mcp_governance_rules',   // Modules/Governance (dono desde a ADR 0363)
    'mcp_projects',           // Forja/Services/ProjectService
    'mcp_project_parts',      // Forja/Services/ProjectDecomposerService
    'mcp_tool_executions',    // Forja/Http/Controllers/Admin/ToolsController
    'mcp_user_module_access', // Forja/Services/UserScopeService
]);

it('dropou o núcleo dual-brain do ADS', function (string $tabela) {
    expect(Schema::hasTable($tabela))->toBeFalse(
        "A tabela `{$tabela}` deveria ter sido dropada pelo E5 (ADR 0363)."
    );
})->with('tabelas_dropadas');

it('preservou as tabelas do ADS que têm consumidor vivo', function (string $tabela) {
    expect(Schema::hasTable($tabela))->toBeTrue(
        "A tabela `{$tabela}` NÃO pode ser dropada: tem consumidor vivo fora do ADS. "
        . 'Dropá-la converte tela viva em SQLSTATE 42S02 → 500.'
    );
})->with('tabelas_preservadas');

it('mantém o detalhe de project de pé sem a tabela de decisões', function () {
    $bizExiste = DB::table('business')->where('id', ADS_DROP_BIZ_WAGNER)->exists();

    if (! $bizExiste) {
        $this->markTestSkipped('business_id=1 ausente neste banco — sem tenant não há o que scopear');
    }

    DB::table('mcp_projects')->where('codigo', ADS_DROP_CODIGO_TESTE)->delete();

    $projectId = DB::table('mcp_projects')->insertGetId([
        'business_id'      => ADS_DROP_BIZ_WAGNER,
        'codigo'           => ADS_DROP_CODIGO_TESTE,
        'nome'             => 'Projeto de contrato do E5',
        'objetivo_macro'   => 'Provar que findDetail não consulta mcp_dual_brain_decisions.',
        'metricas_sucesso' => json_encode([]),
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);

    try {
        $detalhe = (new ProjectService(ADS_DROP_BIZ_WAGNER))->findDetail($projectId);

        // O contrato com ProjectShow.tsx: a prop existe (ela é obrigatória lá e o
        // componente faz `decisions.length`), e vem vazia — não some do payload.
        expect($detalhe)->toHaveKey('decisions')
            ->and($detalhe['decisions'])->toBe([])
            ->and($detalhe['project']['codigo'])->toBe(ADS_DROP_CODIGO_TESTE);
    } finally {
        DB::table('mcp_projects')->where('id', $projectId)->delete();
    }
});
