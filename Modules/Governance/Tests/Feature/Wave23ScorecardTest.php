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

it('Governance BRIEFING.md existe (C3 Reflexividade)', function () {
    $path = base_path('Modules/Governance/BRIEFING.md');
    expect(file_exists($path))->toBeTrue();
});

it('BRIEFING.md referencia ADRs canon (0094, 0155, 0156-0160)', function () {
    $path = base_path('Modules/Governance/BRIEFING.md');
    $content = file_get_contents($path);

    expect($content)->toContain('0094-constituicao-v2');
    expect($content)->toContain('0155-module-grade-v3');
    foreach (['0156', '0157', '0158', '0159', '0160'] as $adr) {
        expect($content)->toContain($adr);
    }
});

it('BRIEFING.md declara dimensions C1-C6 com Δ alvo Wave 23', function () {
    $path = base_path('Modules/Governance/BRIEFING.md');
    $content = file_get_contents($path);

    foreach (['C1', 'C2', 'C3', 'C4', 'C5', 'C6'] as $dim) {
        expect($content)->toContain($dim);
    }
    expect($content)->toContain('Reflexividade');
    expect($content)->toContain('Cobertura');
    expect($content)->toContain('Adoption time');
});

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

it('memory/governance/scorecards/ tem 4 YAMLs canônicos (_template + governance + auditoria + admin)', function () {
    $dir = base_path('memory/governance/scorecards');
    expect(is_dir($dir))->toBeTrue();

    foreach (['_template.yaml', 'governance.yaml', 'auditoria.yaml', 'admin.yaml'] as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        expect(file_exists($path))->toBeTrue("YAML {$file} não existe");
    }
});

it('scorecard governance.yaml tem chaves canônicas (module, slug, type, fsm_n_a, dimensions, ownership)', function () {
    $path = base_path('memory/governance/scorecards/governance.yaml');
    $data = Yaml::parseFile($path);

    foreach (['module', 'slug', 'type', 'fsm_n_a', 'dimensions', 'ownership', 'adrs_referenciadas'] as $key) {
        expect(array_key_exists($key, $data))->toBeTrue("governance.yaml sem chave {$key}");
    }
    expect($data['slug'])->toBe('governance');
    expect($data['type'])->toBe('meta');
    expect($data['fsm_n_a'])->toBeTrue();
});

it('scorecard auditoria.yaml lista whitelist UNREVERTIBLE 5 categorias', function () {
    $path = base_path('memory/governance/scorecards/auditoria.yaml');
    $data = Yaml::parseFile($path);

    expect($data)->toHaveKey('unrevertibles');
    expect($data['unrevertibles'])->toHaveCount(5);
});

it('scorecard admin.yaml declara cross_tenant_intencional=true', function () {
    $path = base_path('memory/governance/scorecards/admin.yaml');
    $data = Yaml::parseFile($path);

    expect($data)->toHaveKey('cross_tenant_intencional');
    expect($data['cross_tenant_intencional'])->toBeTrue();
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
