<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;
use Modules\Governance\Services\AdrReviewBriefLineService;

// EXPLÍCITO (não confiar só no Pest.php do módulo): quando ci.yml roda este
// arquivo direto via .github/ci-sqlite-pest.list, o `uses(...)->in()` do
// Modules/Governance/Tests/Pest.php pode não casar → sem TestCase, sem refresh
// de app por teste → facades (Process/Config) vazam estado entre testes do
// suite gigante (random order). Espelha PlanHealthBriefLineServiceTest.
uses(Tests\TestCase::class);

/**
 * Tests da linha de REVISÃO DE ADR no Daily Brief (ADR 0317 Onda 3 · M3).
 * A sentinela é Node (scripts/governance/memory-health.mjs --json); aqui o
 * shell-out é fakeado (Process::fake) — testamos formatação (teto de vazão
 * top-3) + degradação, não os detectores (esses têm seus próprios testes).
 *
 * @see Modules/Governance/Services/AdrReviewBriefLineService.php
 * @see Modules/Brief/Console/Commands/GenerateBriefCommand.php (plug-point inject)
 */

/**
 * Stub do shell-out Node: devolve o JSON dado no stdout (memory-health --json
 * imprime o JSON e sai 1 só com fails; O/R são warns → exit 0). Catch-all `*`:
 * o serviço faz exatamente uma chamada de processo (o `node ...`).
 */
function fakeAdrReviewHealth(array $json): void
{
    Process::fake([
        '*' => Process::result(
            output: json_encode($json, JSON_UNESCAPED_UNICODE),
            exitCode: count($json['fails'] ?? []) > 0 ? 1 : 0,
        ),
    ]);
}

/** Brief mínimo válido (7 seções + ---END---) pra exercitar o inject(). */
function adrReviewBrief(): string
{
    return "## ESTADO MACRO\n- x\n\n## EM VOO AGORA\n- x\n\n## DECISÕES RECENTES (24h)\n- x\n\n"
        ."## SKILLS USO 7d\n- x\n\n## CHARTERS APODRECENDO\n—\n\n## FLAGS\n- 🟢 Migration aging: ok\n\n"
        ."## METADATA\n- Gerado: hoje\n---END---";
}

it('filas O+R → linha com contadores, top-3 (O primeiro) e excedente (+N)', function () {
    fakeAdrReviewHealth([
        'ok' => true, 'fails' => [], 'warns' => [
            ['check' => 'R', 'kind' => 'revisao-vencida', 'count' => 24, 'sample' => [
                '0020-officeimpresso-grupo-economico', '0068-sprint9-retrieval-ollama-reranker-strategy',
            ], 'msg' => '...'],
            ['check' => 'O', 'kind' => 'morta-mas-canon', 'count' => 5, 'sample' => [
                '0008-sidebar-unica-tabs-horizontais', '0010-sistema-memoria-projeto',
            ], 'msg' => '...'],
        ],
    ]);

    // O antes de R (inconsistência > tempo, ADR 0317) apesar de R vir primeiro no JSON.
    expect((new AdrReviewBriefLineService())->line())
        ->toBe('🟡 Revisão ADR: 5 morta-mas-canon (O) · 24 vencida(s) (R) — top: 0008, 0010, 0020 (+26)');
});

it('só fila R → linha sem a parte O e sem excedente quando cabe no teto', function () {
    fakeAdrReviewHealth([
        'ok' => true, 'fails' => [], 'warns' => [
            ['check' => 'R', 'kind' => 'revisao-vencida', 'count' => 2, 'sample' => [
                '0020-officeimpresso-grupo-economico', '0068-sprint9-retrieval',
            ], 'msg' => '...'],
        ],
    ]);

    expect((new AdrReviewBriefLineService())->line())
        ->toBe('🟡 Revisão ADR: 2 vencida(s) (R) — top: 0020, 0068');
});

it('filas vazias (outros warns não contam) → null', function () {
    fakeAdrReviewHealth([
        'ok' => true, 'fails' => [], 'warns' => [
            ['check' => 'B', 'kind' => 'scorecard-stale', 'count' => 218, 'sample' => [], 'msg' => '...'],
        ],
    ]);

    expect((new AdrReviewBriefLineService())->line())->toBeNull();
});

it('entry *-error da sentinela (check quebrou ≠ fila) → null', function () {
    fakeAdrReviewHealth([
        'ok' => true, 'fails' => [], 'warns' => [
            ['check' => 'R', 'kind' => 'revisao-vencida-error', 'msg' => 'revisao-vencida falhou: boom'],
        ],
    ]);

    expect((new AdrReviewBriefLineService())->line())->toBeNull();
});

it('JSON inválido (node quebrado/ausente) → null', function () {
    Process::fake(['*' => Process::result(output: 'node: not found', exitCode: 127)]);

    expect((new AdrReviewBriefLineService())->line())->toBeNull();
});

it('kill-switch OFF → inject vira no-op mesmo com fila cheia', function () {
    config(['governance.adr_review_brief_line' => false]);
    fakeAdrReviewHealth([
        'ok' => true, 'fails' => [], 'warns' => [
            ['check' => 'O', 'kind' => 'morta-mas-canon', 'count' => 5, 'sample' => ['0008-x'], 'msg' => '...'],
        ],
    ]);

    expect((new AdrReviewBriefLineService())->inject(adrReviewBrief()))->toBe(adrReviewBrief());
});

it('inject coloca a linha como bullet no FLAGS sem quebrar o markdown', function () {
    fakeAdrReviewHealth([
        'ok' => true, 'fails' => [], 'warns' => [
            ['check' => 'O', 'kind' => 'morta-mas-canon', 'count' => 1, 'sample' => ['0136-sells-grade-avancada-modo-toggle'], 'msg' => '...'],
        ],
    ]);

    $out = (new AdrReviewBriefLineService())->inject(adrReviewBrief());

    expect($out)->toContain("## FLAGS\n- 🟡 Revisão ADR: 1 morta-mas-canon (O) — top: 0136")
        ->and($out)->toContain('- 🟢 Migration aging: ok') // bullet original preservado
        ->and(trim($out))->toEndWith('---END---');
});
