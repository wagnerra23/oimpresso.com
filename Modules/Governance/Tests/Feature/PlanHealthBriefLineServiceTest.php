<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;
use Modules\Governance\Services\PlanHealthBriefLineService;

/**
 * Tests da linha de saúde dos PLANOS no Daily Brief (ADR 0294 Onda 1).
 * A sentinela é Node (scripts/governance/plan-health.mjs --json); aqui o
 * shell-out é fakeado (Process::fake) — testamos formatação + degradação, não
 * o parser da sentinela (esse tem seu próprio meta-teste em governança).
 *
 * @see Modules/Governance/Services/PlanHealthBriefLineService.php
 * @see Modules/Brief/Console/Commands/GenerateBriefCommand.php (plug-point inject)
 */

/**
 * Stub do shell-out Node: devolve o JSON dado no stdout (exit 1 quando há 🔴,
 * espelhando o `process.exit(ok?0:1)` da sentinela em modo --json). Catch-all
 * `*`: o serviço faz exatamente uma chamada de processo (o `node ...`).
 */
function fakePlanHealth(array $json): void
{
    $exit = ((int) ($json['fail'] ?? 0)) > 0 ? 1 : 0;

    Process::fake([
        '*' => Process::result(
            output: json_encode($json, JSON_UNESCAPED_UNICODE),
            exitCode: $exit,
        ),
    ]);
}

/** Brief mínimo válido (7 seções + ---END---) pra exercitar o inject(). */
function planHealthBrief(): string
{
    return "## ESTADO MACRO\n- x\n\n## EM VOO AGORA\n- x\n\n## DECISÕES RECENTES (24h)\n- x\n\n"
        ."## SKILLS USO 7d\n- x\n\n## CHARTERS APODRECENDO\n—\n\n## FLAGS\n- 🟢 Migration aging: ok\n\n"
        ."## METADATA\n- Gerado: hoje\n---END---";
}

it('só warns → 🟡 com contagem de planos DISTINTOS (não de achados)', function () {
    fakePlanHealth([
        'ok' => true, 'planos' => 16, 'fail' => 0, 'warn' => 3, 'findings' => [
            ['plan' => 'A', 'issue' => 'sem bloco Status vivo', 'level' => 'warn'],
            ['plan' => 'A', 'issue' => 'reviewed_at ausente', 'level' => 'warn'], // mesmo plano → conta 1
            ['plan' => 'B', 'issue' => 'reviewed_at stale: 40d', 'level' => 'warn'],
        ],
    ]);

    expect((new PlanHealthBriefLineService())->line())
        ->toBe('🟡 Planos: 16 vivos · 0 órfãos · 2 a revisar');
});

it('com órfão (fail) → 🔴', function () {
    fakePlanHealth([
        'ok' => false, 'planos' => 16, 'fail' => 1, 'warn' => 1, 'findings' => [
            ['plan' => 'X', 'issue' => 'órfão: em-execução SEM parent_plan', 'level' => 'fail'],
            ['plan' => 'Y', 'issue' => 'reviewed_at ausente', 'level' => 'warn'],
        ],
    ]);

    expect((new PlanHealthBriefLineService())->line())
        ->toBe('🔴 Planos: 16 vivos · 1 órfãos · 1 a revisar');
});

it('todos saudáveis → 🟢', function () {
    fakePlanHealth(['ok' => true, 'planos' => 8, 'fail' => 0, 'warn' => 0, 'findings' => []]);

    expect((new PlanHealthBriefLineService())->line())
        ->toBe('🟢 Planos: 8 vivos · 0 órfãos · 0 a revisar');
});

it('sentinela em no-op (skipped: PLANS-INDEX ausente) → null', function () {
    fakePlanHealth(['ok' => true, 'skipped' => true, 'reason' => 'PLANS-INDEX ausente', 'findings' => []]);

    expect((new PlanHealthBriefLineService())->line())->toBeNull();
});

it('índice vazio (0 planos) → null', function () {
    fakePlanHealth(['ok' => true, 'planos' => 0, 'fail' => 0, 'warn' => 0, 'findings' => []]);

    expect((new PlanHealthBriefLineService())->line())->toBeNull();
});

it('JSON inválido (node quebrado/ausente) → null', function () {
    Process::fake(['*plan-health.mjs*' => Process::result(output: 'node: not found', exitCode: 127)]);

    expect((new PlanHealthBriefLineService())->line())->toBeNull();
});

it('kill-switch OFF → inject vira no-op mesmo com órfão', function () {
    config(['governance.plan_health_brief_line' => false]);
    fakePlanHealth([
        'ok' => false, 'planos' => 16, 'fail' => 1, 'warn' => 0, 'findings' => [
            ['plan' => 'X', 'issue' => 'órfão', 'level' => 'fail'],
        ],
    ]);

    expect((new PlanHealthBriefLineService())->inject(planHealthBrief()))->toBe(planHealthBrief());
});

it('inject coloca a linha como bullet no FLAGS sem quebrar o markdown', function () {
    fakePlanHealth([
        'ok' => true, 'planos' => 16, 'fail' => 0, 'warn' => 1, 'findings' => [
            ['plan' => 'B', 'issue' => 'reviewed_at stale', 'level' => 'warn'],
        ],
    ]);

    $out = (new PlanHealthBriefLineService())->inject(planHealthBrief());

    expect($out)->toContain("## FLAGS\n- 🟡 Planos: 16 vivos · 0 órfãos · 1 a revisar")
        ->and($out)->toContain('- 🟢 Migration aging: ok') // bullet original preservado
        ->and(trim($out))->toEndWith('---END---');
});
