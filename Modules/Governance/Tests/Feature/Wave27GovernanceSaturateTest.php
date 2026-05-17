<?php

declare(strict_types=1);

use App\Util\OtelHelper;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Modules\Governance\Console\Commands\GovernanceHealthCommand;
use Modules\Governance\Console\Commands\ScorecardSnapshotCommand;
use Modules\Governance\Http\Controllers\DashboardController;
use Modules\Governance\Http\Controllers\ModuleGradeController;
use Modules\Governance\Services\ScopedScorecardEvaluator;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * Wave 27 — Governance auto-saturate ≥95.
 *
 * Cobre delta W27:
 *   - D7 shim `config/retention.governance.php` espelha Modules/Governance/Config/retention.php
 *   - D9 OTel span ATIVO em GovernanceHealthCommand + ScopedScorecardEvaluator
 *   - D6 Inertia::defer CONFIRMADO em ModuleGradeController + DashboardController eager paths
 *   - C3 BRIEFING W27 entry + ADRs 0160+0161 referenciadas
 *   - C5 buckets _INDEX.md atualizado W27 deltas
 *   - CHANGELOG entry W27 presente
 *
 * Unit-level: file system + reflection + zero-cost OTel callbacks (otel.enabled=false).
 * Multi-tenant Tier 0: nenhum DB write — bate só artefatos repo-wide canônicos.
 *
 * NOTA Wave 27: bind explícito de Tests\TestCase (Pest 3.x não auto-descobre
 * Modules/Governance/Tests/Pest.php — só tests/Pest.php é). Sem uses(), File::exists()
 * estoura por container não bootado.
 *
 * @see Modules/Governance/CHANGELOG.md (entry Wave 27)
 * @see config/retention.governance.php (shim novo)
 * @see Modules/Governance/Console/Commands/GovernanceHealthCommand.php (OtelHelper::span)
 */
uses(TestCase::class);

// ---------------------------------------------------------------------------
// D7 — shim config/retention.governance.php (espelho ads + whatsapp pattern)
// ---------------------------------------------------------------------------

it('shim config/retention.governance.php existe', function () {
    $path = base_path('config/retention.governance.php');
    expect(File::exists($path))->toBeTrue("shim {$path} ausente");
});

it('shim retention.governance retorna mesma estrutura do Module config', function () {
    $shim = require base_path('config/retention.governance.php');
    $module = require base_path('Modules/Governance/Config/retention.php');

    expect($shim)->toBeArray();
    expect($shim)->toHaveKey('audit_log_days');
    expect($shim)->toHaveKey('module_grades_days');
    expect($shim)->toHaveKey('action_gate_violations_days');
    expect($shim)->toHaveKey('charter_metrics_days');
    expect($shim)->toHaveKey('pii_redaction_enabled');

    // Valores DEVEM bater (shim apenas re-exporta).
    expect($shim['audit_log_days'])->toBe($module['audit_log_days']);
    expect($shim['module_grades_days'])->toBe($module['module_grades_days']);
});

it('shim retention.governance segue pattern dos shims ads + whatsapp', function () {
    $adsPath = base_path('config/retention.ads.php');
    $waPath  = base_path('config/retention.whatsapp.php');
    $govPath = base_path('config/retention.governance.php');

    foreach ([$adsPath, $waPath, $govPath] as $p) {
        expect(File::exists($p))->toBeTrue("shim {$p} ausente — pattern quebrado");
    }
});

// ---------------------------------------------------------------------------
// D9 OTel — GovernanceHealthCommand wrap span ATIVO (W26+W27)
// ---------------------------------------------------------------------------

it('GovernanceHealthCommand fonte cita OtelHelper::span pra observability', function () {
    $path = base_path('Modules/Governance/Console/Commands/GovernanceHealthCommand.php');
    $content = file_get_contents($path);

    expect($content)->toContain('use App\Util\OtelHelper;');
    expect($content)->toContain('OtelHelper::span(');
    expect($content)->toContain("'governance.health.run'");
});

it('GovernanceHealthCommand --detail roda 4 checks sem crashar (otel zero-cost)', function () {
    config()->set('otel.enabled', false); // zero-cost path no-op

    $exitCode = Artisan::call('governance:health', ['--detail' => true]);

    // Em ambiente teste algumas tabelas podem faltar — exit 0 OU 1 ambos OK.
    expect($exitCode)->toBeIn([0, 1]);

    $output = Artisan::output();
    // Saída --detail deve listar todos os 4 checks canon.
    expect($output)->toContain('policies_enabled');
    expect($output)->toContain('audit_log_alive_24h');
    expect($output)->toContain('module_grades_snapshot');
    expect($output)->toContain('actiongate_mode_active');
});

// ---------------------------------------------------------------------------
// D9 OTel — ScopedScorecardEvaluator wrap span (W24+W27)
// ---------------------------------------------------------------------------

it('ScopedScorecardEvaluator fonte usa OtelHelper::spanBiz pra avaliação', function () {
    $path = base_path('Modules/Governance/Services/ScopedScorecardEvaluator.php');
    $content = file_get_contents($path);

    expect($content)->toContain('use App\Util\OtelHelper;');
    expect($content)->toContain('OtelHelper::spanBiz(');
    expect($content)->toContain("'governance.scorecard.evaluate'");
});

it('ScopedScorecardEvaluator evaluateScorecard executa zero-cost com otel desabilitado', function () {
    config()->set('otel.enabled', false);

    $eval = new ScopedScorecardEvaluator();
    $scorecard = $eval->loadScorecardForModule('Governance');

    $result = $eval->evaluateScorecard('Governance', $scorecard);

    // Resultado preserva estrutura mesmo com OTel no-op.
    expect($result)->toHaveKeys(['module', 'bucket', 'score_total', 'core', 'bucket_dimensions', 'paired_violations', 'evaluated_at']);
    expect($result['module'])->toBe('Governance');
    expect($result['score_total'])->toBeInt();
    expect($result['score_total'])->toBeGreaterThanOrEqual(0);
    expect($result['score_total'])->toBeLessThanOrEqual(100);
});

// ---------------------------------------------------------------------------
// D6 Performance — Inertia::defer em Controllers (confirma RUNBOOK)
// ---------------------------------------------------------------------------

it('ModuleGradeController index aplica Inertia::defer em grades + kpis', function () {
    $path = base_path('Modules/Governance/Http/Controllers/ModuleGradeController.php');
    $content = file_get_contents($path);

    // Garantia da regra RUNBOOK-inertia-defer-pattern: props caras DEVEM ser defer.
    expect($content)->toMatch('/Inertia::defer\(fn \(\) => \$this->buildAllGradesPayload/');
    expect($content)->toMatch('/Inertia::defer\(fn \(\) => \$this->buildKpisPayload/');
});

it('ModuleGradeController show aplica Inertia::defer em history (sparkline 7d)', function () {
    $path = base_path('Modules/Governance/Http/Controllers/ModuleGradeController.php');
    $content = file_get_contents($path);

    expect($content)->toMatch('/Inertia::defer\(fn \(\) => \$this->buildHistoryPayload/');
});

// ---------------------------------------------------------------------------
// C3 Reflexividade — BRIEFING W27 + ADRs 0160+0161
// ---------------------------------------------------------------------------

it('BRIEFING.md referencia ADRs W24+ canônicas (0160 + 0161)', function () {
    $path = base_path('Modules/Governance/BRIEFING.md');
    $content = file_get_contents($path);

    foreach (['0160', '0161'] as $adr) {
        expect($content)->toContain($adr);
    }
});

it('BRIEFING.md declara entry Wave 27 (saturate ≥95)', function () {
    $path = base_path('Modules/Governance/BRIEFING.md');
    $content = file_get_contents($path);

    expect($content)->toContain('Wave 27');
});

// ---------------------------------------------------------------------------
// C5 Cobertura — buckets/_INDEX.md atualizado W27
// ---------------------------------------------------------------------------

it('memory/governance/buckets/_INDEX.md existe (catálogo canônico buckets)', function () {
    $path = base_path('memory/governance/buckets/_INDEX.md');
    expect(File::exists($path))->toBeTrue();
});

it('memory/governance/buckets/_INDEX.md lista buckets ativos (meta_governance + vertical_client_facing)', function () {
    $path = base_path('memory/governance/buckets/_INDEX.md');
    if (! File::exists($path)) {
        $this->markTestSkipped('buckets/_INDEX.md ausente — skip até W27 publicar');
    }
    $content = file_get_contents($path);

    expect($content)->toContain('meta_governance');
    expect($content)->toContain('vertical_client_facing');
});

// ---------------------------------------------------------------------------
// CHANGELOG Wave 27 entry
// ---------------------------------------------------------------------------

it('CHANGELOG.md tem entry Wave 27 (mexeu, registra)', function () {
    $path = base_path('Modules/Governance/CHANGELOG.md');
    $content = file_get_contents($path);

    expect($content)->toContain('Wave 27');
});

// ---------------------------------------------------------------------------
// _INDEX-LIFECYCLE — ADRs 0160, 0161 catalogadas (0162 opcional)
// ---------------------------------------------------------------------------

it('_INDEX-LIFECYCLE.md cataloga ADRs 0160 + 0161 W24/W27 governance v4', function () {
    $path = base_path('memory/decisions/_INDEX-LIFECYCLE.md');
    $content = file_get_contents($path);

    expect($content)->toContain('| 0160 |');
    expect($content)->toContain('| 0161 |');
});

// ---------------------------------------------------------------------------
// Bucket YAML schema sanity (W27 inclui catálogo de buckets canônicos)
// ---------------------------------------------------------------------------

it('bucket meta_governance.yaml tem schema canônico (bucket + target_score + core + paired)', function () {
    $path = base_path('memory/governance/buckets/meta_governance.yaml');
    expect(File::exists($path))->toBeTrue();

    $data = Yaml::parseFile($path);
    expect($data)->toHaveKeys(['bucket', 'target_score', 'core', 'bucket_dimensions', 'paired']);
    expect($data['bucket'])->toBe('meta_governance');
    expect($data['target_score'])->toBeGreaterThanOrEqual(80);
});

it('bucket vertical_client_facing.yaml tem F1_pest_e2e + F2_inertia_defer com paired declarado', function () {
    $path = base_path('memory/governance/buckets/vertical_client_facing.yaml');
    $data = Yaml::parseFile($path);

    expect($data['bucket_dimensions'])->toHaveKeys(['F1_pest_e2e', 'F2_inertia_defer']);
    expect($data['paired'])->toBeArray();
    expect(count($data['paired']))->toBeGreaterThanOrEqual(2);
});

// ---------------------------------------------------------------------------
// Auto-saturate guard (W27 fecha gap residual)
// ---------------------------------------------------------------------------

it('GovernanceServiceProvider registra GovernanceHealthCommand + ScorecardSnapshotCommand', function () {
    $path = base_path('Modules/Governance/Providers/GovernanceServiceProvider.php');
    $content = file_get_contents($path);

    expect($content)->toContain('GovernanceHealthCommand::class');
    expect($content)->toContain('ScorecardSnapshotCommand::class');
});
