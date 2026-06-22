<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;
use Modules\Governance\Services\ShippedLogBriefLineService;

// EXPLÍCITO (não confiar só no Pest.php do módulo): quando ci.yml roda este
// arquivo direto, o uses(...)->in() pode não casar → sem TestCase. Espelha
// PlanHealthBriefLineServiceTest / LeaseBriefSectionServiceTest.
uses(Tests\TestCase::class);

/**
 * Tests da linha de saúde do SHIPPED-LOG no Daily Brief (porta de saída, ADR 0294 ext).
 * A sentinela é Node (scripts/governance/shipped-log-generate.mjs --json); aqui o
 * shell-out é fakeado (Process::fake) — testamos formatação + degradação, não o
 * check de freshness (esse tem seu próprio teste em shipped-log-generate.test.mjs).
 *
 * @see Modules/Governance/Services/ShippedLogBriefLineService.php
 * @see Modules/Brief/Console/Commands/GenerateBriefCommand.php (plug-point inject)
 */

/** Stub do shell-out Node: devolve o JSON dado no stdout (exit 1 quando há stale). */
function fakeShippedLog(array $json): void
{
    $exit = ((int) ($json['stale'] ?? 0)) > 0 ? 1 : 0;

    Process::fake([
        '*' => Process::result(
            output: json_encode($json, JSON_UNESCAPED_UNICODE),
            exitCode: $exit,
        ),
    ]);
}

/** Brief mínimo válido (7 seções + ---END---) pra exercitar o inject(). */
function shippedLogBrief(): string
{
    return "## ESTADO MACRO\n- x\n\n## EM VOO AGORA\n- x\n\n## DECISÕES RECENTES (24h)\n- x\n\n"
        ."## SKILLS USO 7d\n- x\n\n## CHARTERS APODRECENDO\n—\n\n## FLAGS\n- 🟢 Migration aging: ok\n\n"
        ."## METADATA\n- Gerado: hoje\n---END---";
}

it('registro fresco (0 stale) → 🟢', function () {
    fakeShippedLog(['ok' => true, 'cycles' => 1, 'stale' => 0, 'findings' => []]);

    expect((new ShippedLogBriefLineService())->line())
        ->toBe('🟢 Shipped-log: 1 cycle(s) registrado(s) · fresco');
});

it('com cycle desatualizado (stale) → 🔴', function () {
    fakeShippedLog([
        'ok' => false, 'cycles' => 2, 'stale' => 1, 'findings' => [
            ['cycle' => 'CYCLE-07.md', 'issue' => 'generated há 9d (> 4d) — STALE', 'level' => 'fail'],
        ],
    ]);

    expect((new ShippedLogBriefLineService())->line())
        ->toBe('🔴 Shipped-log: 2 cycle(s) · 1 desatualizado(s) (cron parou?)');
});

it('sentinela em no-op (skipped: diretório ausente) → null', function () {
    fakeShippedLog(['ok' => true, 'skipped' => true, 'cycles' => 0, 'findings' => []]);

    expect((new ShippedLogBriefLineService())->line())->toBeNull();
});

it('0 cycles registrados → null', function () {
    fakeShippedLog(['ok' => true, 'cycles' => 0, 'stale' => 0, 'findings' => []]);

    expect((new ShippedLogBriefLineService())->line())->toBeNull();
});

it('JSON inválido (node quebrado/ausente) → null', function () {
    Process::fake(['*' => Process::result(output: 'node: not found', exitCode: 127)]);

    expect((new ShippedLogBriefLineService())->line())->toBeNull();
});

it('kill-switch OFF → inject vira no-op mesmo com stale', function () {
    config(['governance.shipped_log_brief_line' => false]);
    fakeShippedLog([
        'ok' => false, 'cycles' => 1, 'stale' => 1, 'findings' => [
            ['cycle' => 'CYCLE-08.md', 'issue' => 'STALE', 'level' => 'fail'],
        ],
    ]);

    expect((new ShippedLogBriefLineService())->inject(shippedLogBrief()))->toBe(shippedLogBrief());
});

it('inject coloca a linha como bullet no FLAGS sem quebrar o markdown', function () {
    fakeShippedLog(['ok' => true, 'cycles' => 1, 'stale' => 0, 'findings' => []]);

    $out = (new ShippedLogBriefLineService())->inject(shippedLogBrief());

    expect($out)->toContain("## FLAGS\n- 🟢 Shipped-log: 1 cycle(s) registrado(s) · fresco")
        ->and($out)->toContain('- 🟢 Migration aging: ok') // bullet original preservado
        ->and(trim($out))->toEndWith('---END---');
});
