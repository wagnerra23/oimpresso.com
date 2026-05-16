<?php

declare(strict_types=1);

use Modules\Jana\Services\Privacy\PiiRedactor;
use Modules\TeamMcp\Entities\McpActor;
use Spatie\Activitylog\Traits\LogsActivity;

uses(Tests\TestCase::class);

/**
 * Testa D7 LGPD compliance do Módulo TeamMcp — Wave 15 governance v3 RESCUE (2026-05-16).
 *
 * Cobre 3 sub-dimensões da matriz Governance V3:
 * - D7.a (4 pts): PiiRedactor aplicado em Controllers que logam $e->getMessage()
 * - D7.b (3 pts): LogsActivity trait em Entity McpActor (PII: slug/display_name/user_id)
 * - D7.c (3 pts): Retention policy declarada em Config/retention.php
 *
 * Não testa enforcement (job purge ainda em backlog) — testa CONTRATO de declaração.
 *
 * Cross-tenant Tier 0 ([ADR 0093]): tests fictícios biz=99, NUNCA biz=4 (ADR 0101).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see Modules\TeamMcp\Config\retention.php
 */

// ------------------------------------------------------------------
// D7.b — LogsActivity em McpActor (3 pts)
// ------------------------------------------------------------------

it('McpActor usa LogsActivity trait (D7.b LGPD audit trail)', function () {
    $traits = class_uses_recursive(McpActor::class);

    expect($traits)
        ->toHaveKey(LogsActivity::class)
        ->and(method_exists(McpActor::class, 'getActivitylogOptions'))
        ->toBeTrue('McpActor deve implementar getActivitylogOptions()');
});

it('McpActor::getActivitylogOptions retorna LogOptions válido (D7.b smoke)', function () {
    $instance = new McpActor;

    $options = $instance->getActivitylogOptions();

    expect($options)->toBeInstanceOf(\Spatie\Activitylog\LogOptions::class);
});

it('McpActor logOnly NÃO inclui notes (texto livre PII risco)', function () {
    // Smoke: garante que tightening de campos foi aplicado — `notes` é text livre
    // que pode conter CPF/email/etc. logOnly precisa ser explícita whitelist.
    // Extrai o bloco entre `->logOnly([` e o `])` correspondente.
    $sourceFile = base_path('Modules/TeamMcp/Entities/McpActor.php');
    $contents = file_get_contents($sourceFile);

    expect($contents)
        ->toContain('->logOnly([')
        ->toContain("'slug'")
        ->toContain("'display_name'");

    // Recorta APENAS o bloco logOnly([...]) e verifica que 'notes' não está lá
    // (sem confundir com $fillable que também menciona 'notes').
    preg_match('/->logOnly\(\[(.*?)\]\)/s', $contents, $matches);
    expect($matches[1] ?? '')->not->toContain("'notes'");
});

// ------------------------------------------------------------------
// D7.a — PiiRedactor existe + aplicado em Controllers (4 pts)
// ------------------------------------------------------------------

it('PiiRedactor existe e redaciona CPF brasileiro (D7.a smoke)', function () {
    $redactor = app(PiiRedactor::class);

    $input = 'CcIngest erro processando msg do CPF 123.456.789-09';
    $output = $redactor->redact($input);

    expect($output)
        ->toContain('[REDACTED:CPF]')
        ->and($output)->not->toContain('123.456.789-09');
});

it('PiiRedactor redaciona email + telefone juntos (D7.a smoke combo)', function () {
    $redactor = app(PiiRedactor::class);

    $input = 'sync falhou pra dev@oimpresso.com (47) 99876-5432';
    $output = $redactor->redact($input);

    expect($output)
        ->toContain('[REDACTED:EMAIL]')
        ->toContain('[REDACTED:PHONE]')
        ->and($output)
        ->not->toContain('dev@oimpresso.com')
        ->not->toContain('99876-5432');
});

it('PiiRedactor preserva texto sem PII (idempotência)', function () {
    $redactor = app(PiiRedactor::class);

    $input = 'CcIngest 42 messages inseridas com sucesso';
    $output = $redactor->redact($input);

    expect($output)->toBe($input);
});

dataset('teammcp_files_with_pii_redactor', [
    'CcIngestController'        => ['Modules/TeamMcp/Http/Controllers/Mcp/CcIngestController.php'],
    'SyncMemoryWebhookController' => ['Modules/TeamMcp/Http/Controllers/Mcp/SyncMemoryWebhookController.php'],
]);

it('arquivo %s importa PiiRedactor (D7.a aplicação em logs)', function (string $relativePath) {
    $absolutePath = base_path($relativePath);
    expect(file_exists($absolutePath))->toBeTrue("Arquivo {$relativePath} não encontrado");

    $contents = file_get_contents($absolutePath);

    expect($contents)
        ->toContain('use Modules\\Jana\\Services\\Privacy\\PiiRedactor;')
        ->and($contents)
        ->toContain('PiiRedactor::class');
})->with('teammcp_files_with_pii_redactor');

it('TeamMcp não tem Log::error com $e->getMessage() raw sem PiiRedactor (D7.a hardening)', function () {
    $files = collect(glob(base_path('Modules/TeamMcp/Http/Controllers/*.php')))
        ->merge(glob(base_path('Modules/TeamMcp/Http/Controllers/Mcp/*.php')))
        ->merge(glob(base_path('Modules/TeamMcp/Http/Controllers/Admin/*.php')))
        ->merge(glob(base_path('Modules/TeamMcp/Services/*.php')));

    $rawLeaks = [];
    foreach ($files as $file) {
        $contents = file_get_contents($file);
        // Heurística: linhas com `Log::...->error(` + raw `$e->getMessage()` no mesmo
        // bloco SEM `redact(` antes do getMessage. Aproximação simples — false-pos
        // tolerável (basta ter ::redact próximo).
        if (preg_match_all('/Log::[^;]*->error\([^;]*\$e->getMessage\(\)/', $contents, $matches)) {
            foreach ($matches[0] as $match) {
                if (! str_contains($match, 'redact(')) {
                    $rawLeaks[] = basename($file).' → '.substr($match, 0, 100);
                }
            }
        }
    }

    expect($rawLeaks)
        ->toBeEmpty('Esses Log::error ainda vazam PII raw: '.PHP_EOL.implode(PHP_EOL, $rawLeaks));
});

// ------------------------------------------------------------------
// D7.c — Retention policy declarada (3 pts)
// ------------------------------------------------------------------

it('Config/retention.php existe e é array válido (D7.c declaração)', function () {
    $configPath = base_path('Modules/TeamMcp/Config/retention.php');

    expect(file_exists($configPath))->toBeTrue('Modules/TeamMcp/Config/retention.php deve existir');

    $config = require $configPath;

    expect($config)
        ->toBeArray()
        ->toHaveKeys(['enabled', 'entities', 'strategy', 'notice_period_days']);
});

it('retention.php declara TTL pras 6 tabelas TeamMcp PII-relevantes (D7.c cobertura)', function () {
    $config = require base_path('Modules/TeamMcp/Config/retention.php');

    $expectedEntities = [
        'mcp_actors',
        'mcp_tokens',
        'mcp_cc_sessions',
        'mcp_cc_messages',
        'mcp_cc_blobs',
        'mcp_audit_log',
    ];

    foreach ($expectedEntities as $entity) {
        expect(array_key_exists($entity, $config['entities']))
            ->toBeTrue("Tabela {$entity} sem TTL declarado em retention.php");
        expect($config['entities'][$entity])
            ->toBeInt("TTL de {$entity} deve ser inteiro (dias)")
            ->toBeGreaterThan(0, "TTL de {$entity} deve ser positivo");
    }
});

it('retention.php declara strategy ∈ {soft_delete, hard_delete, anonymize}', function () {
    $config = require base_path('Modules/TeamMcp/Config/retention.php');

    expect($config['strategy'])
        ->toBeIn(['soft_delete', 'hard_delete', 'anonymize']);
});

it('mcp_cc_messages tem retention curta (<=180 dias) — PII alta em prompts', function () {
    $config = require base_path('Modules/TeamMcp/Config/retention.php');

    expect((int) $config['entities']['mcp_cc_messages'])
        ->toBeLessThanOrEqual(180, 'mensagens Claude Code com PII colada em prompt — janela curta');
});

it('notice_period_days >= 7 (LGPD Art. 18 §VI aviso prévio mínimo razoável)', function () {
    $config = require base_path('Modules/TeamMcp/Config/retention.php');

    expect((int) $config['notice_period_days'])
        ->toBeGreaterThanOrEqual(7);
});
