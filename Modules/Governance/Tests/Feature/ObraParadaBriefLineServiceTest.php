<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;
use Modules\Governance\Services\ObraParadaBriefLineService;

// EXPLÍCITO (não confiar só no Pest.php do módulo): quando ci.yml roda este
// arquivo direto via .github/ci-sqlite-pest.list, o `uses(...)->in()` do
// Modules/Governance/Tests/Pest.php pode não casar → sem TestCase, facades
// vazam estado. Espelha AdrPendenteBriefLineServiceTest.
uses(Tests\TestCase::class);

/**
 * Tests da FLAG de OBRA PARADA no Daily Brief ("o cron roda" ≠ "o cron entrega").
 * A sentinela é Node (scripts/governance/cron-watchdog.mjs --json, eixo 2); aqui
 * o shell-out é fakeado (Process::fake) — testamos formatação + degradação, não a
 * regra de idade (essa tem selftest hermético próprio no .mjs: `--selftest`).
 *
 * @see Modules/Governance/Services/ObraParadaBriefLineService.php
 * @see scripts/governance/cron-watchdog.mjs (eixo 2 · núcleo puro paradosEntre)
 * @see Modules/Brief/Console/Commands/GenerateBriefCommand.php (plug-point inject)
 */

/** Stub do shell-out Node: devolve o JSON dado no stdout (modo --json sai 0). */
function fakeObraParada(array $json): void
{
    Process::fake([
        '*' => Process::result(output: json_encode($json, JSON_UNESCAPED_UNICODE)),
    ]);
}

/** Payload do eixo 2 com N parados (o 1º é o pior — a sentinela já ordena). */
function obraParadaJson(array $parados): array
{
    return [
        'gate' => 'obra-parada',
        'limite_dias' => 60,
        'total_estado' => 290,
        'total_datados' => 13,
        'parados' => $parados,
    ];
}

/** Brief mínimo válido (7 seções + ---END---) pra exercitar o inject(). */
function obraParadaBrief(): string
{
    return "## ESTADO MACRO\n- x\n\n## EM VOO AGORA\n- x\n\n## DECISÕES RECENTES (24h)\n- x\n\n"
        ."## SKILLS USO 7d\n- x\n\n## CHARTERS APODRECENDO\n—\n\n## FLAGS\n- 🟢 Migration aging: ok\n\n"
        ."## METADATA\n- Gerado: hoje\n---END---";
}

it('artefatos parados → flag com contagem e o PIOR caso nomeado (número solto não diz onde olhar)', function () {
    fakeObraParada(obraParadaJson([
        ['arquivo' => 'memory/governance/scorecards/admin.yaml', 'data' => '2026-05-16', 'dias' => 71],
        ['arquivo' => 'memory/governance/scorecards/auditoria.yaml', 'data' => '2026-05-16', 'dias' => 71],
    ]));

    expect((new ObraParadaBriefLineService())->line())
        ->toBe('🟠 Obra parada: 2 artefato(s) sem atualizar — pior: scorecards/admin.yaml (71d)');
});

it('zero parados → null (flag só existe quando há o que reportar)', function () {
    fakeObraParada(obraParadaJson([]));

    expect((new ObraParadaBriefLineService())->line())->toBeNull();
});

it('JSON de OUTRO gate → null (não confia em payload alheio)', function () {
    fakeObraParada(['gate' => 'adr-proposto-parado', 'A' => [], 'B' => []]);

    expect((new ObraParadaBriefLineService())->line())->toBeNull();
});

it('JSON inválido (node ausente/quebrado) → null', function () {
    Process::fake(['*' => Process::result(output: 'node: not found', exitCode: 127)]);

    expect((new ObraParadaBriefLineService())->line())->toBeNull();
});

it('inject() põe a flag como 1º bullet de ## FLAGS e preserva o resto do brief', function () {
    fakeObraParada(obraParadaJson([
        ['arquivo' => 'memory/governance/scorecards/admin.yaml', 'data' => '2026-05-16', 'dias' => 71],
    ]));

    $out = (new ObraParadaBriefLineService())->inject(obraParadaBrief());

    expect($out)->toContain('🟠 Obra parada: 1 artefato(s)')
        ->and($out)->toContain('🟢 Migration aging: ok')   // não come o bullet que já existia
        ->and($out)->toContain('---END---')                 // marcador final intacto
        ->and(substr_count($out, '## FLAGS'))->toBe(1);     // não duplica a seção
});

it('inject() devolve o brief INTACTO quando não há parados (brief nunca quebra por causa da flag)', function () {
    fakeObraParada(obraParadaJson([]));

    $brief = obraParadaBrief();

    expect((new ObraParadaBriefLineService())->inject($brief))->toBe($brief);
});

it('kill-switch OFF → no-op mesmo com parados', function () {
    config()->set('governance.obra_parada_brief_line', false);
    fakeObraParada(obraParadaJson([
        ['arquivo' => 'memory/governance/scorecards/admin.yaml', 'data' => '2026-05-16', 'dias' => 71],
    ]));

    $brief = obraParadaBrief();

    expect((new ObraParadaBriefLineService())->inject($brief))->toBe($brief);
});

it('encurta o path pros 2 últimos segmentos (o brief tem teto de tokens)', function () {
    fakeObraParada(obraParadaJson([
        ['arquivo' => 'a/b/c/d/deadlink-baseline.json', 'data' => '2026-01-01', 'dias' => 200],
    ]));

    expect((new ObraParadaBriefLineService())->line())->toContain('d/deadlink-baseline.json');
});
