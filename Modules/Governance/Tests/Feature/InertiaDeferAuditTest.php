<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Regression: Controllers Governance usam Inertia::defer() em props caras.
 *
 * Pattern canônico: skill `inertia-defer-default` (Tier B) — toda prop com
 * paginate/count/with-eager/aggregated/Service-DB/HTTP-externo deve ser
 * `Inertia::defer(fn () => $this->buildXxxPayload(...))` em vez de eager.
 *
 * Validado D-14 (Inbox switch conversa): 300ms → 50ms (-83%) com defer pulando
 * closures não-solicitadas em partial reload (`only:[...]`).
 *
 * Ref: memory/requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md
 *      memory/proibicoes.md §"Sempre fazer" item Inertia::defer DEFAULT
 *      Modules/Governance/Http/Controllers/ModuleGradeController.php (exemplo canônico)
 *
 * Estratégia: grep no source dos Controllers — se chamam Inertia::render(),
 * DEVEM ter `Inertia::defer(` no método index() OU justificar via comentário.
 * Detecta drift quando alguém adiciona prop nova eager sem defer.
 */

/**
 * @return string Conteúdo do arquivo Controller (source code).
 */
function readGovernanceController(string $name): string
{
    $path = __DIR__ . "/../../Http/Controllers/{$name}.php";
    expect(file_exists($path))->toBeTrue("Controller {$name} deveria existir em Modules/Governance/Http/Controllers/");

    return (string) file_get_contents($path);
}

dataset('controllers_inertia_render', [
    // Controllers que retornam Inertia::Response e devem usar defer em props caras.
    'DashboardController'     => ['DashboardController'],
    'AuditController'         => ['AuditController'],
    'DriftAlertsController'   => ['DriftAlertsController'],
    'PoliciesController'      => ['PoliciesController'],
    'ModuleGradeController'   => ['ModuleGradeController'],
]);

it('controller usa Inertia::defer em pelo menos uma prop', function (string $name) {
    $source = readGovernanceController($name);

    expect($source)
        ->toContain('Inertia::defer(', "{$name} deveria usar Inertia::defer() pra props caras (skill inertia-defer-default). Pattern: Inertia::defer(fn () => \$this->buildXxxPayload(...))")
        ->toContain('Inertia::render(', "{$name} deveria retornar Inertia::render() — sanity check do dataset");
})->with('controllers_inertia_render');

it('controller tem pelo menos um método privado buildXxxPayload', function (string $name) {
    $source = readGovernanceController($name);

    expect($source)->toMatch(
        '/private function build[A-Z][A-Za-z]*Payload\s*\(/',
        "{$name} deveria ter pelo menos um método `private function buildXxxPayload(...)` pra encapsular a lógica diferida (skill inertia-defer-default). Convenção canônica do ModuleGradeController."
    );
})->with('controllers_inertia_render');

it('DashboardController não deixa pending_adrs/audit_highlights eager (regression D-14)', function () {
    $source = readGovernanceController('DashboardController');

    // Props que costumavam ser eager (queries DB pesadas) devem agora estar dentro
    // de uma chamada Inertia::defer.
    expect($source)
        ->toContain("'pending_adrs'      => Inertia::defer(", 'pending_adrs deveria ser deferred (DB query mcp_memory_documents)')
        ->toContain("'audit_highlights'  => Inertia::defer(", 'audit_highlights deveria ser deferred (DB query mcp_audit_log 24h)')
        ->toContain("'kpis'              => Inertia::defer(", 'kpis deveria ser deferred (5 COUNT queries agregadas)');
});

it('AuditController defere entries + kpis mas mantém filters eager (UI state)', function () {
    $source = readGovernanceController('AuditController');

    expect($source)
        ->toContain("'entries'             => Inertia::defer(", 'entries deveria ser deferred (DB query mcp_audit_log)')
        ->toContain("'kpis'                => Inertia::defer(", 'kpis deveria ser deferred (agregação derivada de entries)');

    // filters é state da UI — deve ficar eager (não pode ter Inertia::defer próximo).
    // Heurística: linha "'filters'" não pode conter "Inertia::defer" na mesma linha.
    $lines = explode("\n", $source);
    $filterLines = array_filter($lines, fn ($l) => str_contains($l, "'filters'"));
    foreach ($filterLines as $line) {
        expect($line)->not->toContain('Inertia::defer(', "filters é UI state (target de partial reload) — deve ficar eager. Linha violadora: {$line}");
    }
});

it('ModuleGradeController permanece exemplo canônico (não regrediu)', function () {
    $source = readGovernanceController('ModuleGradeController');

    // Sentinel — se alguém "simplificar" voltando ao eager, o test pega.
    expect($source)
        ->toContain('Inertia::defer(fn () => $this->buildAllGradesPayload())', 'grades defer canônico')
        ->toContain('Inertia::defer(fn () => $this->buildKpisPayload())', 'kpis defer canônico');
});
