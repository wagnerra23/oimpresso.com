<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;
use Modules\Brief\Services\BriefValidator;
use Modules\Governance\Services\AgentOutcomeBriefSectionService;

// EXPLÍCITO (não confiar só no Pest.php do módulo): quando ci.yml roda este
// arquivo direto via .github/ci-sqlite-pest.list, o `uses(...)->in()` do
// Modules/Governance/Tests/Pest.php pode não casar → sem TestCase, facades
// vazam estado. Espelha AdrReviewBriefLineServiceTest.
uses(Tests\TestCase::class);

/**
 * Tests da seção OUTCOME DO AGENTE (7d) no Daily Brief (US-GOV-052).
 * O medidor é Node (scripts/governance/agent-pr-outcomes.mjs --json, DORA dos
 * PRs do agente); aqui o shell-out é fakeado (Process::fake) — testamos
 * formatação (2 janelas, tendência) + degradação, não as métricas (essas têm
 * selftest hermético próprio no .mjs / governance-script-tests.yml).
 *
 * @see Modules/Governance/Services/AgentOutcomeBriefSectionService.php
 * @see Modules/Brief/Console/Commands/GenerateBriefCommand.php (plug-point inject)
 */

/**
 * Stub das DUAS execuções (--days 7 e --days 30): closure inspeciona o comando
 * e devolve o fixture da janela pedida. `$json30 = null` simula baseline
 * indisponível (stdout não-JSON).
 */
function fakeAgentOutcome(?array $json7, ?array $json30 = null): void
{
    Process::fake([
        '*' => function ($process) use ($json7, $json30) {
            $cmd = is_array($process->command) ? implode(' ', $process->command) : (string) $process->command;
            $json = str_contains($cmd, '--days 30') ? $json30 : $json7;

            return Process::result(output: $json === null ? 'gh: not found' : json_encode($json, JSON_UNESCAPED_UNICODE));
        },
    ]);
}

/** Fixture no shape do agent-pr-outcomes.mjs --json (buildReport). */
function agentOutcomeReport(int $terminais, int $merged, ?float $acceptRate, ?float $cfr, int $failures, ?float $medianH, ?float $p90H): array
{
    return [
        'ok' => true,
        'generated' => '2026-07-09',
        'window' => ['since' => '2026-07-02', 'days' => 7],
        'agent' => ['marker' => '[CC]', 'total_terminais' => $terminais, 'mergeados' => $merged],
        'metrics' => [
            'time_to_merge' => ['count' => $merged, 'median_h' => $medianH, 'p90_h' => $p90H],
            'accept' => ['merged' => $merged, 'rejected' => $terminais - $merged, 'accept_rate' => $acceptRate],
            'change_failure' => ['merged_count' => $merged, 'failures' => $failures, 'cfr' => $cfr, 'hits' => []],
        ],
        'confianca' => 'proxy (DORA de PR via gh; ver gaps)',
        'gaps' => [],
    ];
}

/** Brief mínimo válido (7 seções + ---END---) pra exercitar o inject(). */
function agentOutcomeBrief(): string
{
    return "## ESTADO MACRO\n- x\n\n## EM VOO AGORA\n- x\n\n## DECISÕES RECENTES (24h)\n- x\n\n"
        ."## SKILLS USO 7d\n- x\n\n## CHARTERS APODRECENDO\n—\n\n## FLAGS\n- 🟢 Migration aging: ok\n\n"
        ."## METADATA\n- Gerado: hoje\n---END---";
}

it('duas janelas ok → seção com os 3 números DORA (7d) + tendência vs 30d', function () {
    fakeAgentOutcome(
        agentOutcomeReport(56, 54, 96.4, 3.7, 2, 2.3, 18),
        agentOutcomeReport(210, 198, 94.0, 6.0, 12, 3.1, 24),
    );

    expect((new AgentOutcomeBriefSectionService())->section())->toBe(
        "## OUTCOME DO AGENTE (7d)\n"
        .'- 7d: 56 PRs terminais · aceitação 96,4% (54/56) · change-failure 3,7% (2/54 c/ hotfix ≤48h) · TTM med 2,3h · p90 18h'
        ."\n".'- Tendência vs 30d: aceitação 94%→96,4% ▲ · CFR 6%→3,7% ▼ · TTM med 3,1h→2,3h ▼'
    );
});

it('baseline 30d indisponível → seção sai só com a linha 7d (sem tendência)', function () {
    fakeAgentOutcome(agentOutcomeReport(10, 9, 90.0, 11.1, 1, 4.0, 20), null);

    $section = (new AgentOutcomeBriefSectionService())->section();

    expect($section)->toContain('aceitação 90% (9/10)')
        ->and($section)->not->toContain('Tendência');
});

it('métrica null no medidor (ex.: 0 mergeados) → n/d, sem quebrar', function () {
    // 2 terminais, ambos rejeitados: accept_rate 0, cfr/TTM null (sem merge).
    fakeAgentOutcome(agentOutcomeReport(2, 0, 0.0, null, 0, null, null), null);

    expect((new AgentOutcomeBriefSectionService())->section())
        ->toContain('aceitação 0% (0/2) · change-failure n/d (0/0 c/ hotfix ≤48h) · TTM med n/d · p90 n/d');
});

it('0 PRs terminais na janela 7d → null (nada a reportar)', function () {
    fakeAgentOutcome(agentOutcomeReport(0, 0, null, null, 0, null, null));

    expect((new AgentOutcomeBriefSectionService())->section())->toBeNull();
});

it('JSON inválido na janela 7d (node/gh ausentes) → null', function () {
    fakeAgentOutcome(null, null);

    expect((new AgentOutcomeBriefSectionService())->section())->toBeNull();
});

it('kill-switch OFF → inject vira no-op mesmo com dados', function () {
    config(['governance.agent_outcome_brief_section' => false]);
    fakeAgentOutcome(agentOutcomeReport(56, 54, 96.4, 3.7, 2, 2.3, 18));

    expect((new AgentOutcomeBriefSectionService())->inject(agentOutcomeBrief()))->toBe(agentOutcomeBrief());
});

it('inject insere a seção antes de ## FLAGS e o brief continua VÁLIDO no BriefValidator', function () {
    fakeAgentOutcome(
        agentOutcomeReport(56, 54, 96.4, 3.7, 2, 2.3, 18),
        agentOutcomeReport(210, 198, 94.0, 6.0, 12, 3.1, 24),
    );

    $out = (new AgentOutcomeBriefSectionService())->inject(agentOutcomeBrief());

    expect($out)->toContain("## OUTCOME DO AGENTE (7d)\n- 7d: 56 PRs terminais")
        ->and($out)->toMatch('/## OUTCOME DO AGENTE \(7d\).*\n\n## FLAGS/s') // seção ANTES de FLAGS
        ->and($out)->toContain('- 🟢 Migration aging: ok');

    // Âncora de contrato (ADR 0091/0226): header extra NÃO pode invalidar o brief —
    // BriefValidator exige os 7 headers canônicos em ordem + ---END--- + ≤8k tokens.
    expect((new BriefValidator())->validate($out)->isOk())->toBeTrue();
});
