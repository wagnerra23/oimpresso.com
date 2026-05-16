<?php

declare(strict_types=1);

use Modules\Jana\Entities\CacheSemantico;
use Modules\Jana\Entities\Conversa;
use Modules\Jana\Entities\HealthNarrative;
use Modules\Jana\Entities\MemoriaFato;
use Modules\Jana\Entities\Meta;
use Modules\Jana\Entities\Sugestao;
use Modules\Jana\Services\Privacy\PiiRedactor;
use Spatie\Activitylog\Traits\LogsActivity;

uses(Tests\TestCase::class);

/**
 * D7 LGPD compliance — Módulo Jana — Wave 10 push (2026-05-16).
 *
 * Cobre 3 sub-dimensões da matriz Governance V3:
 * - D7.a (4 pts): PiiRedactor wired em logs/exceptions de Services de IA
 * - D7.b (3 pts): LogsActivity trait em Models que tocam PII / governança
 * - D7.c (3 pts): Retention policy declarada (Config/retention.php canônico)
 *
 * Não testa enforcement (purge job ainda em backlog ADR 0105 sinal qualificado)
 * — testa CONTRATO de declaração.
 *
 * Cross-tenant Tier 0 ([ADR 0093]): tests usam smoke estrutural (não tocam DB
 * cliente real). ADR 0101: NUNCA biz=4 (ROTA LIVRE — cliente Larissa).
 * Vizra rejeitada ([ADR 0048]) — testes diretos via Pest sem framework externo.
 * Zero auto-mem ([ADR 0061]) — referência canônica via git apenas.
 *
 * @see memory/decisions/0035-stack-ai-canonica-wagner-2026-04-26.md
 * @see memory/decisions/0048-framework-agentes-laravel-ai-vizra-rejeitada.md
 * @see memory/decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see Modules\Jana\Config\retention.php
 */

// ------------------------------------------------------------------
// D7.b — LogsActivity em Models PII-relevantes / governança (3 pts)
// ------------------------------------------------------------------

dataset('jana_pii_models', [
    'MemoriaFato (já tinha — referência canônica)' => [MemoriaFato::class],
    'Conversa (Wave 10)'                            => [Conversa::class],
    'Sugestao (Wave 10)'                            => [Sugestao::class],
    'Meta (Wave 10)'                                => [Meta::class],
    'CacheSemantico (Wave 10)'                      => [CacheSemantico::class],
    'HealthNarrative (Wave 10)'                     => [HealthNarrative::class],
]);

it('Model %s usa LogsActivity trait (D7.b LGPD audit trail)', function (string $modelClass) {
    $traits = class_uses_recursive($modelClass);

    expect($traits)
        ->toHaveKey(LogsActivity::class)
        ->and(method_exists($modelClass, 'getActivitylogOptions'))
        ->toBeTrue("Modelo {$modelClass} deve implementar getActivitylogOptions()");
})->with('jana_pii_models');

// ------------------------------------------------------------------
// D7.a — PiiRedactor wired em Services críticos (4 pts)
// ------------------------------------------------------------------

dataset('jana_services_with_pii_redactor', [
    'LaravelAiSdkDriver (Anthropic prompt+response)' => [
        'Modules/Jana/Services/Ai/LaravelAiSdkDriver.php',
    ],
    'ConversationSummarizer (exception logging)' => [
        'Modules/Jana/Services/Memoria/ConversationSummarizer.php',
    ],
]);

it('Service %s importa PiiRedactor (D7.a aplicação em logs)', function (string $relativePath) {
    $absolutePath = base_path($relativePath);
    expect(file_exists($absolutePath))->toBeTrue("Arquivo {$relativePath} não encontrado");

    $contents = file_get_contents($absolutePath);

    expect($contents)
        ->toContain('PiiRedactor');
})->with('jana_services_with_pii_redactor');

it('LaravelAiSdkDriver tem helper redactErrorMessage (D7.a exception sanitization)', function () {
    $contents = file_get_contents(base_path('Modules/Jana/Services/Ai/LaravelAiSdkDriver.php'));

    expect($contents)
        ->toContain('protected function redactErrorMessage')
        ->and($contents)
        ->toContain('PiiRedactor::class')
        ->and($contents)
        ->toContain('redactErrorMessage($e)');
});

it('PiiRedactor canônico cobre 5 tipos PII BR (CPF/CNPJ/EMAIL/CEP/PHONE)', function () {
    $redactor = new PiiRedactor();

    $samples = [
        'cliente CPF 123.456.789-00 entrou'                  => '[REDACTED:CPF]',
        'empresa CNPJ 12.345.678/0001-90'                    => '[REDACTED:CNPJ]',
        'email cliente@empresa.com.br confirmou'             => '[REDACTED:EMAIL]',
        'CEP 01310-100 endereço'                             => '[REDACTED:CEP]',
        'telefone +55 11 98765-4321 contato'                 => '[REDACTED:PHONE]',
    ];

    foreach ($samples as $input => $expectedPlaceholder) {
        $result = $redactor->redact($input);
        expect($result)->toContain($expectedPlaceholder);
    }
});

it('ConversationSummarizer redacta exception antes de log (D7.a hardening)', function () {
    $contents = file_get_contents(
        base_path('Modules/Jana/Services/Memoria/ConversationSummarizer.php'),
    );

    // Pattern aceitável: exception passa por PiiRedactor antes de virar log
    expect($contents)
        ->toContain('PiiRedactor')
        ->and($contents)
        ->toContain('errSanitizado');
});

// ------------------------------------------------------------------
// D7.c — Retention policy declarada (3 pts)
// ------------------------------------------------------------------

it('Config/retention.php existe e é array válido (D7.c declaração)', function () {
    $configPath = base_path('Modules/Jana/Config/retention.php');

    expect(file_exists($configPath))->toBeTrue();

    $config = require $configPath;

    expect($config)
        ->toBeArray()
        ->toHaveKeys(['enabled', 'entities', 'strategy', 'notice_period_days']);
});

it('retention.php declara TTL pras entidades Jana PII/IA-relevantes (D7.c cobertura)', function () {
    $config = require base_path('Modules/Jana/Config/retention.php');

    $expectedEntities = [
        'conversa', 'mensagem', 'sugestao', 'cache_semantico', 'memoria_fato',
        'memoria_metrica', 'brief_diario', 'health_narrative', 'mcp_audit_log',
        'embedder_index',
    ];

    foreach ($expectedEntities as $entity) {
        expect(array_key_exists($entity, $config['entities']))
            ->toBeTrue("Entidade {$entity} sem TTL declarado em retention.php");
    }
});

it('retention.php declara strategy ∈ {soft_delete, hard_delete, anonymize}', function () {
    $config = require base_path('Modules/Jana/Config/retention.php');

    expect($config['strategy'])
        ->toBeIn(['soft_delete', 'hard_delete', 'anonymize']);
});

it('retention.php mensagem audit fiscal >=1825d / 5 anos (ADR 0094 §4 custo IA tracking)', function () {
    $config = require base_path('Modules/Jana/Config/retention.php');

    expect((int) $config['entities']['mensagem'])
        ->toBeGreaterThanOrEqual(1825, 'mensagem com tokens_in/out é fonte de audit custo IA — manter ≥5y');
});

it('retention.php embedder_index/cache_semantico ≤90d (derivados regeneráveis)', function () {
    $config = require base_path('Modules/Jana/Config/retention.php');

    expect((int) $config['entities']['cache_semantico'])
        ->toBeLessThanOrEqual(90, 'cache_semantico é derivado regenerável — purge agressivo');

    expect((int) $config['entities']['embedder_index'])
        ->toBeLessThanOrEqual(90, 'embedder_index Meilisearch é derivado — purge agressivo');
});
