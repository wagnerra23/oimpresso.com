<?php

declare(strict_types=1);

use Modules\Jana\Services\Privacy\PiiRedactor;

uses(Tests\TestCase::class);

/**
 * Testa D7 LGPD compliance do Módulo ADS — Wave 15 push (2026-05-16).
 *
 * Cobre 3 sub-dimensões da matriz Governance V3:
 * - D7.a (4 pts): PiiRedactor aplicado nos pontos de log/persistência
 * - D7.b (3 pts): ADS é query-builder puro (sem Eloquent Models) — declarado
 *                 explicitamente em module.json (`activity_log_enabled=false` +
 *                 nota explicativa); audit append-only é via mcp_dual_brain_decisions
 *                 (resolved_by/resolved_at) e sale_stage_history (FSM canônico)
 * - D7.c (3 pts): retention policy declarada em Config/retention.php + module.json
 *
 * Não testa enforcement (purge job ainda em backlog) — testa CONTRATO de declaração.
 *
 * Cross-tenant Tier 0 ([ADR 0093]): smoke usa biz=1 (Wagner WR2) + biz=99 (fictício).
 * ADR 0101: NUNCA biz=4 (ROTA LIVRE — cliente Larissa).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see Modules\ADS\Config\retention.php
 */

// ------------------------------------------------------------------
// D7.a — PiiRedactor wrap nos Services LLM (4 pts)
// ------------------------------------------------------------------

it('PiiRedactor existe e redaciona CPF brasileiro', function () {
    $redactor = app(PiiRedactor::class);

    $input = 'falha brain_b decision payload CPF 123.456.789-09 invalid';
    $output = $redactor->redact($input);

    expect($output)
        ->toContain('[REDACTED:CPF]')
        ->and($output)
        ->not->toContain('123.456.789-09');
});

it('PiiRedactor redaciona email + telefone juntos em mensagem de exception', function () {
    $redactor = app(PiiRedactor::class);

    $input = 'planner fail enviar pra cliente@exemplo.com.br (11) 98765-4321';
    $output = $redactor->redact($input);

    expect($output)
        ->toContain('[REDACTED:EMAIL]')
        ->toContain('[REDACTED:PHONE]')
        ->and($output)
        ->not->toContain('cliente@exemplo.com.br')
        ->not->toContain('98765-4321');
});

it('PiiRedactor preserva texto sem PII (idempotência)', function () {
    $redactor = app(PiiRedactor::class);

    $input = 'Brain B processed decision 42 in 1340ms';
    $output = $redactor->redact($input);

    expect($output)->toBe($input);
});

dataset('ads_services_with_pii_redactor', [
    'BrainBService'           => ['Modules/ADS/Services/BrainBService.php'],
    'PlannerService'          => ['Modules/ADS/Services/PlannerService.php'],
    'ProjectDecomposerService' => ['Modules/ADS/Services/ProjectDecomposerService.php'],
    'ReviewerService'         => ['Modules/ADS/Services/ReviewerService.php'],
]);

it('service %s importa PiiRedactor (D7.a aplicação em logs)', function (string $relativePath) {
    $absolutePath = base_path($relativePath);
    expect(file_exists($absolutePath))->toBeTrue("Arquivo {$relativePath} não encontrado");

    $contents = file_get_contents($absolutePath);

    expect($contents)
        ->toContain('use Modules\\Jana\\Services\\Privacy\\PiiRedactor;')
        ->and($contents)
        ->toContain('PiiRedactor::class');
})->with('ads_services_with_pii_redactor');

it('ADS não tem mais Log com $e->getMessage() raw sem PiiRedactor (D7.a hardening)', function () {
    $files = collect(glob(base_path('Modules/ADS/Services/*.php')));

    $rawLeaks = [];
    foreach ($files as $file) {
        $contents = file_get_contents($file);
        // Pattern raw: Log::*(...$e->getMessage()...) sem PiiRedactor mencionado no MESMO bloco catch
        // Heurística simples: se arquivo loga $e->getMessage() E não importa PiiRedactor, é leak
        if (preg_match('/Log::[a-z]+\([^;]*\$e->getMessage\(\)/', $contents)
            && ! str_contains($contents, 'PiiRedactor')) {
            $rawLeaks[] = basename($file);
        }
    }

    expect($rawLeaks)
        ->toBeEmpty('Esses services ADS ainda vazam $e->getMessage() raw: '.implode(', ', $rawLeaks));
});

// ------------------------------------------------------------------
// D7.b — Audit trail declarado (3 pts)
// ------------------------------------------------------------------

it('module.json declara estratégia de audit trail (D7.b transparência)', function () {
    $manifestPath = base_path('Modules/ADS/module.json');
    $manifest = json_decode(file_get_contents($manifestPath), true);

    expect($manifest)
        ->toHaveKey('lgpd_compliance');

    expect($manifest['lgpd_compliance'])
        ->toHaveKeys(['pii_fields_tracked', 'pii_redactor_enabled', 'activity_log_enabled']);

    // ADS é query-builder puro — declaração transparente
    expect($manifest['lgpd_compliance']['pii_redactor_enabled'])->toBeTrue();

    // Quando activity_log_enabled=false, módulo DEVE explicar por quê (transparência)
    expect($manifest['lgpd_compliance'])->toHaveKey('activity_log_note');
    expect($manifest['lgpd_compliance']['activity_log_note'])
        ->toBeString()
        ->not->toBeEmpty();
});

it('mcp_dual_brain_decisions tem colunas de audit append-only (D7.b alternativa Spatie)', function () {
    if (\DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL UltimatePOS');
    }
    if (! \Schema::hasTable('mcp_dual_brain_decisions')) {
        $this->markTestSkipped('Tabela mcp_dual_brain_decisions ausente — rode migrate Modules/ADS');
    }

    expect(\Schema::hasColumn('mcp_dual_brain_decisions', 'resolved_at'))->toBeTrue();
    expect(\Schema::hasColumn('mcp_dual_brain_decisions', 'resolved_by'))->toBeTrue();
    expect(\Schema::hasColumn('mcp_dual_brain_decisions', 'business_id'))->toBeTrue();
});

// ------------------------------------------------------------------
// D7.c — Retention policy declarada (3 pts)
// ------------------------------------------------------------------

it('Config/retention.php existe e é array válido (D7.c declaração)', function () {
    $configPath = base_path('Modules/ADS/Config/retention.php');

    expect(file_exists($configPath))->toBeTrue();

    $config = require $configPath;

    expect($config)
        ->toBeArray()
        ->toHaveKeys(['enabled', 'entities', 'strategy', 'notice_period_days']);
});

it('retention.php declara TTL pras entidades sensíveis canon (D7.c cobertura)', function () {
    $config = require base_path('Modules/ADS/Config/retention.php');

    $expectedEntities = [
        'ads_brain_b_outputs',
        'ads_escalations',
        'ads_tool_executions',
        'ads_confidence_scores',
        'ads_project_parts',
    ];

    foreach ($expectedEntities as $entity) {
        expect(array_key_exists($entity, $config['entities']))
            ->toBeTrue("Entidade {$entity} sem TTL declarado em retention.php");
    }
});

it('retention.php declara strategy ∈ {soft_delete, hard_delete, anonymize}', function () {
    $config = require base_path('Modules/ADS/Config/retention.php');

    expect($config['strategy'])
        ->toBeIn(['soft_delete', 'hard_delete', 'anonymize']);
});

it('brain_b_outputs tem retention <=730 dias (LGPD texto livre LLM)', function () {
    $config = require base_path('Modules/ADS/Config/retention.php');

    expect((int) $config['entities']['ads_brain_b_outputs'])->toBeLessThanOrEqual(730);
});

it('module.json declara retention_days + retention_config (D7.c metadata)', function () {
    $manifestPath = base_path('Modules/ADS/module.json');
    $manifest = json_decode(file_get_contents($manifestPath), true);

    expect($manifest)
        ->toHaveKey('retention_days')
        ->toHaveKey('retention_config')
        ->toHaveKey('lgpd_compliance');

    expect($manifest['retention_config'])->toBe('Modules/ADS/Config/retention.php');
});
