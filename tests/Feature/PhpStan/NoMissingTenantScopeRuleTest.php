<?php

declare(strict_types=1);

/**
 * Pest test estrutural — NoMissingTenantScopeRule (ADR 0208 custom + ADR 0093 Tier 0, US-INFRA-017).
 *
 * Cobertura:
 *  1. Rule class existe + namespace App\PhpStan\Rules
 *  2. Implementa interface PHPStan\Rules\Rule
 *  3. Registrada em phpstan.neon.dist via services + tag phpstan.rules.rule
 *  4. getNodeType retorna ClassMethod::class
 *  5. ELOQUENT_METHODS lista contém query/where/find/all/create/etc
 *  6. Path filter restrito a Modules/*/Http/Controllers/*Controller.php
 *  7. Skip métodos privados + __construct
 *  8. Heurística business_id mention aceita 3 formas
 *  9. RuleErrorBuilder usa identifier 'oimpresso.missingTenantScope'
 * 10. Mensagem cita ADR 0093 + T-AP-2/T-AP-8 (rastreabilidade canon)
 * 11. Baseline absorveu >=10 hits pre-existentes
 */

const TENANT_RULE_PATH = 'app/PhpStan/Rules/NoMissingTenantScopeRule.php';
const TENANT_PHPSTAN_CONFIG = 'phpstan.neon.dist';
const TENANT_BASELINE = 'phpstan-baseline.neon';

function readTenantRule(): string
{
    return file_get_contents(base_path(TENANT_RULE_PATH));
}

function readTenantPhpstanConfig(): string
{
    return file_get_contents(base_path(TENANT_PHPSTAN_CONFIG));
}

function readTenantBaseline(): string
{
    return file_get_contents(base_path(TENANT_BASELINE));
}

it('US-INFRA-017 — NoMissingTenantScopeRule.php existe', function () {
    expect(file_exists(base_path(TENANT_RULE_PATH)))->toBeTrue();
});

it('US-INFRA-017 — namespace App\\PhpStan\\Rules', function () {
    $src = readTenantRule();
    expect($src)->toContain('namespace App\\PhpStan\\Rules;');
});

it('US-INFRA-017 — implementa PHPStan\\Rules\\Rule interface', function () {
    $src = readTenantRule();
    expect($src)->toContain('use PHPStan\\Rules\\Rule;');
    expect($src)->toMatch('/class\s+NoMissingTenantScopeRule\s+implements\s+Rule/');
});

it('US-INFRA-017 — getNodeType retorna ClassMethod::class', function () {
    $src = readTenantRule();
    expect($src)->toContain('return ClassMethod::class;');
});

it('US-INFRA-017 — ELOQUENT_METHODS lista canônica de queries Eloquent', function () {
    $src = readTenantRule();
    foreach (['query', 'where', 'find', 'all', 'create', 'firstOrCreate', 'updateOrCreate'] as $method) {
        expect($src)->toContain("'$method'");
    }
});

it('US-INFRA-017 — path filter restrito a Modules/*/Http/Controllers/*', function () {
    $src = readTenantRule();
    expect($src)->toContain("'/Modules/'");
    expect($src)->toContain("'/Http/Controllers/'");
    expect($src)->toContain("'Controller.php'");
});

it('US-INFRA-017 — skip métodos não-públicos + __construct', function () {
    $src = readTenantRule();
    expect($src)->toContain('isPublic()');
    expect($src)->toContain("__construct");
});

it('US-INFRA-017 — heurística aceita business_id / businessId / BusinessScope', function () {
    $src = readTenantRule();
    expect($src)->toContain("'business_id'");
    expect($src)->toContain("'businessId'");
    expect($src)->toContain("'BusinessScope'");
});

it('US-INFRA-017 — usa identifier oimpresso.missingTenantScope', function () {
    $src = readTenantRule();
    expect($src)->toContain("->identifier('oimpresso.missingTenantScope')");
});

it('US-INFRA-017 — mensagem cita ADR 0093 Tier 0 + T-AP-2/T-AP-8', function () {
    $src = readTenantRule();
    expect($src)->toContain('ADR 0093');
    expect($src)->toContain('T-AP-2');
    expect($src)->toContain('T-AP-8');
});

it('US-INFRA-017 — registrada em phpstan.neon.dist via services + tag', function () {
    $src = readTenantPhpstanConfig();
    expect($src)->toContain('App\\PhpStan\\Rules\\NoMissingTenantScopeRule');
    // Confirma tag phpstan.rules.rule
    $count = substr_count($src, 'phpstan.rules.rule');
    expect($count)->toBeGreaterThanOrEqual(2); // NoSilentFallback + NoMissingTenant
});

it('US-INFRA-017 — baseline absorveu hits pre-existentes (>=10 ocorrências oimpresso.missingTenantScope)', function () {
    $baseline = readTenantBaseline();
    expect($baseline)->toContain('oimpresso.missingTenantScope');
    // ~116 esperados em codebase legacy UPOS
    $count = substr_count($baseline, 'oimpresso.missingTenantScope');
    expect($count)->toBeGreaterThanOrEqual(10);
});

it('US-INFRA-017 — usa NodeFinder pra varrer body do método (heurística string-match)', function () {
    $src = readTenantRule();
    expect($src)->toContain('PhpParser\\NodeFinder');
    expect($src)->toContain('new NodeFinder()');
});
