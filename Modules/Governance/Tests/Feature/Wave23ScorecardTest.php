<?php

declare(strict_types=1);

use Modules\Governance\Console\Commands\ScorecardSnapshotCommand;
use Symfony\Component\Yaml\Yaml;

uses(Tests\TestCase::class);

/**
 * Wave 23 — Governance C3 + C5 + C6 (target 74 → ≥90).
 *
 * Cobre artefatos novos da Wave:
 *   - C3 Reflexividade: BRIEFING.md presente + ADRs canon listadas
 *   - C5 Cobertura: 4 YAMLs scorecard em memory/governance/scorecards/
 *   - C5 Cobertura: ScorecardSnapshotCommand existe + registrado
 *   - C6 Adoption time: skill governance-pr-summary SKILL.md presente
 *
 * Unit-level (não toca DB). Cobre filesystem + registrations.
 *
 * @see Modules/Governance/BRIEFING.md
 * @see memory/governance/scorecards/governance.yaml
 * @see .claude/skills/governance-pr-summary/SKILL.md
 */

// ------------------------------------------------------------------
// C3 Reflexividade — BRIEFING.md + ADRs referenciadas
// ------------------------------------------------------------------

// 2026-07-30 ([W] "quero mover tudo para memory, apagar os outros e revisar os vinculos"):
// a lapide-ponteiro Modules/Governance/BRIEFING.md foi DELETADA. A casa unica do BRIEFING
// e memory/requisitos/<X>/BRIEFING.md — mesmo path que o gate REQUIRED
// `briefing-coverage-required.yml` ja consulta. O teste segue provando C3 Reflexividade,
// agora contra o dono canonico em vez do stub.
it('Governance BRIEFING.md existe (C3 Reflexividade)', function () {
    $path = base_path('memory/requisitos/Governance/BRIEFING.md');
    expect(file_exists($path))->toBeTrue();
});

// ADR 0345 (aceito [W] 2026-07-21): Modules/Governance/BRIEFING.md virou lápide-ponteiro —
// a casa única do BRIEFING é memory/requisitos/Governance/BRIEFING.md. As asserções de conteúdo
// Wave 23 (ADRs 0094/0155/0156-0160 + dimensões C1-C6) eram sobre o conteúdo agora deprecado;
// esses marcadores de saturação nunca migraram pro canônico (verificado 2026-07-21). Removidas
// por obsolescência. A existência do arquivo (lápide) segue coberta pelo teste acima.

it('module.json declara governance.bucket fsm_n_a (C3 contract)', function () {
    $path = base_path('Modules/Governance/module.json');
    $content = file_get_contents($path);
    $json = json_decode($content, true);

    expect($json)->toHaveKey('governance');
    expect($json['governance'])->toHaveKey('fsm_n_a');
    expect($json['governance']['fsm_n_a'])->toBeTrue();
});

// ------------------------------------------------------------------
// C5 Cobertura — 4 YAMLs scorecard
// ------------------------------------------------------------------

// 2026-09-25: os scorecards de módulo (governance, auditoria, admin, vestuario,
// comunicacaovisual) foram aposentados por decisão [W]; só o _template.yaml fica.
it('memory/governance/scorecards/ mantém o _template.yaml', function () {
    $path = base_path('memory/governance/scorecards/_template.yaml');
    expect(file_exists($path))->toBeTrue();
});

it('_template.yaml fornece estrutura referência sem dados reais', function () {
    $path = base_path('memory/governance/scorecards/_template.yaml');
    $data = Yaml::parseFile($path);

    // Template tem chave 'module' mas é placeholder
    expect($data)->toHaveKey('module');
    expect($data['last_grade'])->toBeNull();
});

// ------------------------------------------------------------------
// C5 — ScorecardSnapshotCommand
// ------------------------------------------------------------------

it('ScorecardSnapshotCommand classe existe + tem signature governance:scorecard-snapshot', function () {
    expect(class_exists(ScorecardSnapshotCommand::class))->toBeTrue();

    $cmd = new ScorecardSnapshotCommand();
    $reflection = new \ReflectionClass($cmd);
    $prop = $reflection->getProperty('signature');
    $prop->setAccessible(true);
    $signature = $prop->getValue($cmd);

    expect($signature)->toContain('governance:scorecard-snapshot');
});

it('ScorecardSnapshotCommand usa --detail (NÃO --verbose — Symfony reserved)', function () {
    $path = base_path('Modules/Governance/Console/Commands/ScorecardSnapshotCommand.php');
    $content = file_get_contents($path);

    expect($content)->toContain('--detail');

    // Inspecionar APENAS o signature (não docblock — comentários explicativos podem citar --verbose)
    expect($content)->toMatch('/\{--detail\b/');
    expect($content)->not->toMatch('/\{--verbose\b/');
});

it('ScorecardSnapshotCommand registrado no GovernanceServiceProvider', function () {
    $path = base_path('Modules/Governance/Providers/GovernanceServiceProvider.php');
    $content = file_get_contents($path);

    expect($content)->toContain('ScorecardSnapshotCommand::class');
});

// ------------------------------------------------------------------
// C6 Adoption time — skill governance-pr-summary
// ------------------------------------------------------------------

it('skill governance-pr-summary SKILL.md existe (C6 Adoption time)', function () {
    $path = base_path('.claude/skills/governance-pr-summary/SKILL.md');
    expect(file_exists($path))->toBeTrue();
});

it('skill governance-pr-summary tem frontmatter tier B + parent_adr 0094', function () {
    $path = base_path('.claude/skills/governance-pr-summary/SKILL.md');
    $content = file_get_contents($path);

    expect($content)->toContain('tier: B');
    expect($content)->toContain('parent_adr: 0094');
    expect($content)->toContain('name: governance-pr-summary');
});

it('skill governance-pr-summary descreve trigger ANTES de gh pr create (regra de ouro)', function () {
    $path = base_path('.claude/skills/governance-pr-summary/SKILL.md');
    $content = file_get_contents($path);

    expect($content)->toContain('gh pr create');
    expect($content)->toContain('Module Grade');
});
