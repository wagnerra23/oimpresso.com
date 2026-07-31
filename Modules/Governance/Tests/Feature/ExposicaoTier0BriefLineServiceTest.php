<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;
use Modules\Governance\Services\ExposicaoTier0BriefLineService;

// EXPLÍCITO (não confiar só no Pest.php do módulo): quando ci.yml roda este
// arquivo direto via .github/ci-sqlite-pest.list, o `uses(...)->in()` do
// Modules/Governance/Tests/Pest.php pode não casar → sem TestCase, sem refresh
// de app por teste → facades (Process/Config) vazam estado entre testes do
// suite gigante (random order). Espelha PlanHealthBriefLineServiceTest.
uses(Tests\TestCase::class);

/**
 * Tests da linha de EXPOSIÇÃO TIER-0 × cobertura no Daily Brief.
 * A sentinela é Node (scripts/qa/exposicao-tier0.mjs --stdout); aqui o shell-out
 * é fakeado (Process::fake) — testamos formatação + degradação, não a varredura
 * da sentinela (essa tem o baseline + o workflow semanal como defesa própria).
 *
 * @see Modules/Governance/Services/ExposicaoTier0BriefLineService.php
 * @see Modules/Forja/Console/Commands/GenerateBriefCommand.php (plug-point inject)
 */

/**
 * Stub do shell-out Node: devolve o JSON dado no stdout. `--stdout` sai 0 sempre
 * (não altera exit code, diferente de `--json`). Catch-all `*`: o serviço faz
 * exatamente uma chamada de processo (o `node ...`).
 */
function fakeExposicao(array $json): void
{
    Process::fake([
        '*' => Process::result(
            output: json_encode($json, JSON_UNESCAPED_UNICODE),
            exitCode: 0,
        ),
    ]);
}

/**
 * Snapshot mínimo no shape que a sentinela emite em `--stdout`.
 *
 * @param  array<string, mixed>  $trend
 */
function exposicaoSnapshot(int $hot, int $debt, ?array $trend = null, string $topo = 'Sells/Show.tsx'): array
{
    return [
        'aggregates' => [
            'universe' => 243,
            'tier0_hot' => $hot,
            'hot_covered' => $hot - $debt,
            'hot_debt' => $debt,
            'by_category' => [],
        ],
        'debt_ranked' => $debt > 0
            ? [['screen' => $topo, 'module' => 'Sells', 'categories' => ['dinheiro', 'estoque'], 'exposure_score' => 11]]
            : [],
        'trend' => $trend,
    ];
}

/** Brief mínimo válido (7 seções + ---END---) pra exercitar o inject(). */
function exposicaoBrief(): string
{
    return "## ESTADO MACRO\n- x\n\n## EM VOO AGORA\n- x\n\n## DECISÕES RECENTES (24h)\n- x\n\n"
        ."## SKILLS USO 7d\n- x\n\n## CHARTERS APODRECENDO\n—\n\n## FLAGS\n- 🟢 Migration aging: ok\n\n"
        ."## METADATA\n- Gerado: hoje\n---END---";
}

it('débito estável → 🟡 com D/H e a tela do topo do ranking', function () {
    fakeExposicao(exposicaoSnapshot(
        hot: 124,
        debt: 120,
        trend: ['hot_debt_delta' => 0, 'hot_covered_delta' => 0, 'tier0_hot_delta' => 0, 'piso_regrediu' => false],
        topo: 'Compras/components/Drawer.tsx',
    ));

    expect((new ExposicaoTier0BriefLineService())->line())
        ->toBe('🟡 Exposição Tier-0: 120/124 quentes sem teste · topo: Compras/components/Drawer.tsx');
});

it('débito CRESCEU (tela quente nova sem teste) → 🔴 com delta', function () {
    fakeExposicao(exposicaoSnapshot(
        hot: 124,
        debt: 120,
        trend: ['hot_debt_delta' => 3, 'hot_covered_delta' => 0, 'tier0_hot_delta' => 3, 'piso_regrediu' => false],
    ));

    expect((new ExposicaoTier0BriefLineService())->line())
        ->toBe('🔴 Exposição Tier-0: 120/124 quentes sem teste (Δ+3) · topo: Sells/Show.tsx');
});

it('PISO Tier-0 regrediu (cobertura caiu) → 🔴 mesmo com débito não-crescente', function () {
    // Piso só sobe (ADR 0256): uma tela coberta que perde o teste é regressão,
    // ainda que o débito total não tenha crescido.
    fakeExposicao(exposicaoSnapshot(
        hot: 124,
        debt: 120,
        trend: ['hot_debt_delta' => 0, 'hot_covered_delta' => -2, 'tier0_hot_delta' => 0, 'piso_regrediu' => true],
    ));

    expect((new ExposicaoTier0BriefLineService())->line())
        ->toBe('🔴 Exposição Tier-0: 120/124 quentes sem teste · topo: Sells/Show.tsx');
});

it('débito caiu → 🟡 com delta negativo (progresso visível)', function () {
    fakeExposicao(exposicaoSnapshot(
        hot: 124,
        debt: 117,
        trend: ['hot_debt_delta' => -3, 'hot_covered_delta' => 3, 'tier0_hot_delta' => 0, 'piso_regrediu' => false],
    ));

    expect((new ExposicaoTier0BriefLineService())->line())
        ->toBe('🟡 Exposição Tier-0: 117/124 quentes sem teste (Δ-3) · topo: Sells/Show.tsx');
});

it('débito zero → 🟢 (sem tela de topo)', function () {
    fakeExposicao(exposicaoSnapshot(hot: 124, debt: 0, trend: null));

    expect((new ExposicaoTier0BriefLineService())->line())
        ->toBe('🟢 Exposição Tier-0: 0/124 quentes sem teste');
});

it('zero telas quentes → null (ausência de medição não é saúde)', function () {
    // Varredura sem match (universo vazio / script rodando noutro cwd) devolveria
    // "0/0". Reportar isso como 🟢 seria afirmar saúde a partir de não-medição.
    fakeExposicao(exposicaoSnapshot(hot: 0, debt: 0, trend: null));

    expect((new ExposicaoTier0BriefLineService())->line())->toBeNull();
});

it('sem baseline (trend null) → linha sai, apenas sem delta', function () {
    fakeExposicao(exposicaoSnapshot(hot: 10, debt: 4, trend: null));

    expect((new ExposicaoTier0BriefLineService())->line())
        ->toBe('🟡 Exposição Tier-0: 4/10 quentes sem teste · topo: Sells/Show.tsx');
});

it('JSON inválido (node quebrado/ausente) → null', function () {
    // Catch-all '*' (mesma chave de fakeExposicao): chaves consistentes entre
    // testes evitam handlers acumulados/ambíguos no Process::fake.
    Process::fake(['*' => Process::result(output: 'node: not found', exitCode: 127)]);

    expect((new ExposicaoTier0BriefLineService())->line())->toBeNull();
});

it('relatório de TEXTO no stdout (flag errada) → null, não linha com lixo', function () {
    // Controle-negativo do contrato do `--stdout`: se algum dia o modo voltar a
    // imprimir o relatório humano, o json_decode falha e a linha desaparece —
    // nunca injeta texto de relatório no brief.
    Process::fake(['*' => Process::result(
        output: "\n=== Exposição Tier-0 × cobertura de comportamento · 243 telas ===\n",
        exitCode: 0,
    )]);

    expect((new ExposicaoTier0BriefLineService())->line())->toBeNull();
});

it('kill-switch OFF → inject vira no-op mesmo com piso regredido', function () {
    config(['governance.exposicao_tier0_brief_line' => false]);
    fakeExposicao(exposicaoSnapshot(
        hot: 124,
        debt: 120,
        trend: ['hot_debt_delta' => 5, 'hot_covered_delta' => -1, 'tier0_hot_delta' => 4, 'piso_regrediu' => true],
    ));

    expect((new ExposicaoTier0BriefLineService())->inject(exposicaoBrief()))->toBe(exposicaoBrief());
});

it('inject coloca a linha como bullet no FLAGS sem quebrar o markdown', function () {
    fakeExposicao(exposicaoSnapshot(
        hot: 124,
        debt: 120,
        trend: ['hot_debt_delta' => 0, 'hot_covered_delta' => 0, 'tier0_hot_delta' => 0, 'piso_regrediu' => false],
    ));

    $out = (new ExposicaoTier0BriefLineService())->inject(exposicaoBrief());

    expect($out)->toContain("## FLAGS\n- 🟡 Exposição Tier-0: 120/124 quentes sem teste")
        ->and($out)->toContain('- 🟢 Migration aging: ok') // bullet original preservado
        ->and(trim($out))->toEndWith('---END---');
});

it('brief sem seção FLAGS → devolve intacto (não inventa seção)', function () {
    fakeExposicao(exposicaoSnapshot(hot: 124, debt: 120, trend: null));

    $semFlags = "## ESTADO MACRO\n- x\n\n## METADATA\n- Gerado: hoje\n---END---";

    expect((new ExposicaoTier0BriefLineService())->inject($semFlags))->toBe($semFlags);
});
