<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;
use Modules\Governance\Services\AdrPendenteBriefLineService;

// EXPLÍCITO (não confiar só no Pest.php do módulo): quando ci.yml roda este
// arquivo direto via .github/ci-sqlite-pest.list, o `uses(...)->in()` do
// Modules/Governance/Tests/Pest.php pode não casar → sem TestCase, facades
// vazam estado. Espelha AdrReviewBriefLineServiceTest.
uses(Tests\TestCase::class);

/**
 * Tests da FLAG de ADR PENDENTE no Daily Brief (US-GOV-052).
 * A sentinela é Node (scripts/governance/adr-proposto-parado.mjs --json); aqui
 * o shell-out é fakeado (Process::fake) — testamos formatação + degradação,
 * não os checks A/B/C (esses têm selftest hermético próprio no .mjs).
 *
 * @see Modules/Governance/Services/AdrPendenteBriefLineService.php
 * @see Modules/Brief/Console/Commands/GenerateBriefCommand.php (plug-point inject)
 */

/** Stub do shell-out Node: devolve o JSON dado no stdout (reporter — exit 0 sem --strict). */
function fakeAdrPendente(array $json): void
{
    Process::fake([
        '*' => Process::result(output: json_encode($json, JSON_UNESCAPED_UNICODE)),
    ]);
}

/** Brief mínimo válido (7 seções + ---END---) pra exercitar o inject(). */
function adrPendenteBrief(): string
{
    return "## ESTADO MACRO\n- x\n\n## EM VOO AGORA\n- x\n\n## DECISÕES RECENTES (24h)\n- x\n\n"
        ."## SKILLS USO 7d\n- x\n\n## CHARTERS APODRECENDO\n—\n\n## FLAGS\n- 🟢 Migration aging: ok\n\n"
        ."## METADATA\n- Gerado: hoje\n---END---";
}

it('pendências A+B+C → flag com os 3 contadores (C via C_total, array capado em 15)', function () {
    fakeAdrPendente([
        'gate' => 'adr-proposto-parado', 'dias' => 14,
        'A' => [['arquivo' => 'proposals/0320-x.md', 'status' => 'aceito', 'check' => 'A']],
        'B' => [['arquivo' => 'proposals/0314-y.md', 'check' => 'B'], ['arquivo' => 'proposals/0319-z.md', 'check' => 'B']],
        'C' => [['arquivo' => '0299-w.md', 'check' => 'C', 'idadeDias' => 17], ['arquivo' => '0301-v.md', 'check' => 'C', 'idadeDias' => 15]],
        'C_total' => 5,
    ]);

    expect((new AdrPendenteBriefLineService())->line())->toBe('🟠 ADR pendente: A:1 B:2 C:5');
});

it('zero pendências (A+B+C = 0) → null — flag só existe quando N>0', function () {
    fakeAdrPendente(['gate' => 'adr-proposto-parado', 'dias' => 14, 'A' => [], 'B' => [], 'C' => [], 'C_total' => 0]);

    expect((new AdrPendenteBriefLineService())->line())->toBeNull();
});

it('JSON inválido (node quebrado/ausente) → null', function () {
    Process::fake(['*' => Process::result(output: 'node: not found', exitCode: 127)]);

    expect((new AdrPendenteBriefLineService())->line())->toBeNull();
});

it('JSON de outro gate (output inesperado) → null', function () {
    fakeAdrPendente(['gate' => 'outro-gate', 'A' => [['x' => 1]]]);

    expect((new AdrPendenteBriefLineService())->line())->toBeNull();
});

it('kill-switch OFF → inject vira no-op mesmo com pendência', function () {
    config(['governance.adr_pendente_brief_line' => false]);
    fakeAdrPendente([
        'gate' => 'adr-proposto-parado', 'dias' => 14,
        'A' => [['arquivo' => 'proposals/0320-x.md']], 'B' => [], 'C' => [], 'C_total' => 0,
    ]);

    expect((new AdrPendenteBriefLineService())->inject(adrPendenteBrief()))->toBe(adrPendenteBrief());
});

it('inject coloca a flag como bullet no FLAGS sem quebrar o markdown', function () {
    fakeAdrPendente([
        'gate' => 'adr-proposto-parado', 'dias' => 14,
        'A' => [], 'B' => [['arquivo' => 'proposals/0314-y.md']], 'C' => [['arquivo' => '0299-w.md']], 'C_total' => 1,
    ]);

    $out = (new AdrPendenteBriefLineService())->inject(adrPendenteBrief());

    expect($out)->toContain("## FLAGS\n- 🟠 ADR pendente: A:0 B:1 C:1")
        ->and($out)->toContain('- 🟢 Migration aging: ok') // bullet original preservado
        ->and(trim($out))->toEndWith('---END---');
});
