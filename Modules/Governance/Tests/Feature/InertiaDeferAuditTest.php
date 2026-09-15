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
 *      Modules/Governance/Http/Controllers/DashboardController.php (exemplo canônico —
 *      o ModuleGradeController era o exemplo até ser aposentado pela ADR 0399, 2026-09-15)
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
]);

it('controller usa Inertia::defer em pelo menos uma prop', function (string $name) {
    $source = readGovernanceController($name);

    expect($source)
        // FALHA AQUI SIGNIFICA: {$name} deveria usar Inertia::defer() pra props caras (skill inertia-defer-default). Pattern: Inertia::defer(fn () => \$this->buildXxxPayload(...))
        ->toContain('Inertia::defer(')
        // FALHA AQUI SIGNIFICA: {$name} deveria retornar Inertia::render() — sanity check do dataset
        ->toContain('Inertia::render(');
})->with('controllers_inertia_render');

it('controller tem pelo menos um método privado buildXxxPayload', function (string $name) {
    $source = readGovernanceController($name);

    expect($source)->toMatch(
        '/private function build[A-Z][A-Za-z]*Payload\s*\(/',
        "{$name} deveria ter pelo menos um método `private function buildXxxPayload(...)` pra encapsular a lógica diferida (skill inertia-defer-default). Convenção canônica dos controllers deste registry."
    );
})->with('controllers_inertia_render');

it('DashboardController não deixa pending_adrs/audit_highlights eager (regression D-14)', function () {
    $source = readGovernanceController('DashboardController');

    // Props que costumavam ser eager (queries DB pesadas) devem agora estar dentro
    // de uma chamada Inertia::defer.
    expect($source)
        // FALHA AQUI SIGNIFICA: pending_adrs deveria ser deferred (DB query mcp_memory_documents)
        ->toContain("'pending_adrs'      => Inertia::defer(")
        // FALHA AQUI SIGNIFICA: audit_highlights deveria ser deferred (DB query mcp_audit_log 24h)
        ->toContain("'audit_highlights'  => Inertia::defer(")
        // FALHA AQUI SIGNIFICA: kpis deveria ser deferred (5 COUNT queries agregadas)
        ->toContain("'kpis'              => Inertia::defer(");
});

it('AuditController defere entries + kpis mas mantém filters eager (UI state)', function () {
    $source = readGovernanceController('AuditController');

    expect($source)
        // FALHA AQUI SIGNIFICA: entries deveria ser deferred (DB query mcp_audit_log)
        ->toContain("'entries'             => Inertia::defer(")
        // FALHA AQUI SIGNIFICA: kpis deveria ser deferred (agregação derivada de entries)
        ->toContain("'kpis'                => Inertia::defer(");

    // filters é state da UI — deve ficar eager (não pode ter Inertia::defer próximo).
    // Heurística: linha "'filters'" não pode conter "Inertia::defer" na mesma linha.
    $lines = explode("\n", $source);
    $filterLines = array_filter($lines, fn ($l) => str_contains($l, "'filters'"));
    foreach ($filterLines as $line) {
        expect($line)->not->toContain('Inertia::defer(', "filters é UI state (target de partial reload) — deve ficar eager. Linha violadora: {$line}");
    }
});

// O sentinela do ModuleGradeController saiu com a ADR 0399 (2026-09-15) — o controller foi
// deletado. Os testes PARAMETRIZADOS acima seguem cobrindo os 4 controllers restantes
// (Inertia::defer presente + buildXxxPayload + filters eager), que é o contrato de verdade;
// o sentinela só fixava as DUAS props específicas daquela tela. NÃO repontei o assert pra
// outro controller: isso inventaria um contrato que ninguém pediu, e errar o nome do método
// daria um vermelho sem dono.
