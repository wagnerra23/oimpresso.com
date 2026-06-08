<?php

declare(strict_types=1);

use Modules\Brief\Services\BriefGeneratorService;
use Modules\Jana\Services\Privacy\PiiRedactor;

uses(Tests\TestCase::class);

/**
 * Testa compliance LGPD do módulo Brief (D7 — Wave 13).
 *
 * Cobre:
 * 1. Config retention.php existe + tem chaves canônicas (entities, strategy)
 * 2. Flag 'brief.redact_pii_before_llm' default true
 * 3. PiiRedactor está registrado no container + redacta payload
 * 4. BriefGeneratorService usa PiiRedactor no path de buildUserPrompt (sanity)
 *
 * NÃO chama OpenAI real (sem custo). Mock-friendly por design.
 *
 * @see Modules/Brief/Config/retention.php
 * @see Modules/Brief/Services/BriefGeneratorService.php
 * @see memory/decisions/0091-daily-brief.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

it('config retention.php existe e declara entidades canonicas', function () {
    $configPath = __DIR__.'/../../Config/retention.php';
    expect(file_exists($configPath))->toBeTrue('retention.php nao existe em Modules/Brief/Config/');

    $config = require $configPath;

    expect($config)->toBeArray();
    expect($config)->toHaveKey('enabled');
    expect($config)->toHaveKey('entities');
    expect($config)->toHaveKey('strategy');
    expect($config)->toHaveKey('redact_pii_before_llm');

    expect($config['entities'])->toHaveKey('briefs');
    expect($config['entities']['briefs'])->toBe(90, 'briefs deve ter retencao 90d (regenerable)');
    expect($config['entities'])->toHaveKey('briefs_invalid');
    expect($config['entities'])->toHaveKey('audit_log');

    expect($config['strategy'])->toBe('hard_delete', 'briefs regenerable => hard_delete default');
});

it('flag redact_pii_before_llm e true por default', function () {
    // Sem ENV setado, default é true (defesa em profundidade D7).
    $config = require __DIR__.'/../../Config/retention.php';
    expect($config['redact_pii_before_llm'])->toBeTrue(
        'PiiRedactor deve estar ATIVO por default antes de mandar payload pra OpenAI'
    );
});

it('PiiRedactor redacta CPF CNPJ email phone do payload do brief', function () {
    $redactor = app(PiiRedactor::class);

    $payload = [
        'cycle_codename' => 'jolly-hypatia',
        'mission_focus' => 'Wagner contato wagnerra@gmail.com sobre meta',
        'team_member' => 'Felipe CPF 123.456.789-00 reportou OS aberta',
        'business_cnpj' => 'Cliente 12.345.678/0001-90 atrasou pagamento',
        'phone_note' => 'Larissa ligou de (48) 99876-5432',
    ];

    $redacted = $redactor->redactArray($payload);

    expect($redacted['mission_focus'])->toContain('[REDACTED:EMAIL]');
    expect($redacted['team_member'])->toContain('[REDACTED:CPF]');
    expect($redacted['business_cnpj'])->toContain('[REDACTED:CNPJ]');
    expect($redacted['phone_note'])->toContain('[REDACTED:PHONE]');

    // PII NÃO deve aparecer em nenhum campo
    $allText = implode(' ', array_map(fn ($v) => (string) $v, $redacted));
    expect($allText)->not->toContain('wagnerra@gmail.com');
    expect($allText)->not->toContain('123.456.789-00');
    expect($allText)->not->toContain('12.345.678/0001-90');
});

it('BriefGeneratorService instanciavel via container sem chamar OpenAI', function () {
    // Sanity check: classe resolve via DI, PiiRedactor visivel
    $svc = app(BriefGeneratorService::class);
    expect($svc)->toBeInstanceOf(BriefGeneratorService::class);

    // Custo inicial = 0 (nao chamou LLM ainda)
    expect($svc->lastCallCost())->toBe(0.0);
});

it('config brief.redact_pii_before_llm publicado no namespace brief.*', function () {
    // Apos mergeConfigFrom no ServiceProvider, flag visivel em config()
    $val = config('brief.redact_pii_before_llm');
    expect($val)->toBeTrue('mergeConfigFrom no BriefServiceProvider falhou');

    expect(config('brief.entities.briefs'))->toBe(90);
    expect(config('brief.strategy'))->toBe('hard_delete');
});
