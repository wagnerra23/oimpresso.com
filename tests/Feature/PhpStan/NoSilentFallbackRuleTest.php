<?php

declare(strict_types=1);

/**
 * Pest test estrutural — NoSilentFallbackRule (ADR 0212 Camada 3, US-INFRA-020).
 *
 * Cobertura:
 *  1. Rule class existe + namespace App\PhpStan\Rules
 *  2. Implementa interface PHPStan\Rules\Rule
 *  3. Registrada em phpstan.neon.dist via services + tag phpstan.rules.rule
 *  4. getNodeType retorna If_::class
 *  5. processNode detecta empty() + ! isset() conditions
 *  6. processNode detecta Log::warning/error/critical/alert/emergency calls
 *  7. RuleErrorBuilder usa identifier 'oimpresso.silentFallback'
 *  8. Baseline phpstan-baseline.neon absorveu hits pre-existentes (≥1 ocorrência)
 *
 * Validação prod (smoke pós-merge): novo PR com `if (empty(...)) $x = default;`
 * SEM Log::warning falha workflow phpstan-gate.yml com identifier
 * `oimpresso.silentFallback`.
 */

const RULE_PATH = 'app/PhpStan/Rules/NoSilentFallbackRule.php';
const PHPSTAN_CONFIG_PATH = 'phpstan.neon.dist';
const PHPSTAN_BASELINE_PATH = 'phpstan-baseline.neon';

function readRule(): string
{
    return file_get_contents(base_path(RULE_PATH));
}

function readPhpstanConfig(): string
{
    return file_get_contents(base_path(PHPSTAN_CONFIG_PATH));
}

function readPhpstanBaseline(): string
{
    return file_get_contents(base_path(PHPSTAN_BASELINE_PATH));
}

it('US-INFRA-020 — NoSilentFallbackRule.php existe', function () {
    expect(file_exists(base_path(RULE_PATH)))->toBeTrue();
});

it('US-INFRA-020 — namespace App\\PhpStan\\Rules', function () {
    $src = readRule();
    expect($src)->toContain('namespace App\\PhpStan\\Rules;');
});

it('US-INFRA-020 — implementa PHPStan\\Rules\\Rule interface', function () {
    $src = readRule();
    expect($src)->toContain('use PHPStan\\Rules\\Rule;');
    expect($src)->toMatch('/class\s+NoSilentFallbackRule\s+implements\s+Rule/');
});

it('US-INFRA-020 — getNodeType retorna If_::class', function () {
    $src = readRule();
    expect($src)->toContain('return If_::class;');
});

it('US-INFRA-020 — processNode detecta empty() condition (Node\\Expr\\Empty_)', function () {
    $src = readRule();
    expect($src)->toContain('Node\\Expr\\Empty_');
});

it('US-INFRA-020 — processNode detecta ! isset() condition (BooleanNot + Isset_)', function () {
    $src = readRule();
    expect($src)->toContain('Node\\Expr\\BooleanNot');
    expect($src)->toContain('Node\\Expr\\Isset_');
});

it('US-INFRA-020 — detecta Log::warning/error/critical/alert/emergency', function () {
    $src = readRule();
    foreach (['warning', 'error', 'critical', 'alert', 'emergency'] as $method) {
        expect($src)->toContain("'$method'");
    }
});

it('US-INFRA-020 — detecta Log facade (Log + Illuminate\\Support\\Facades\\Log)', function () {
    $src = readRule();
    expect($src)->toContain("'Log'");
    expect($src)->toContain('Illuminate\\\\Support\\\\Facades\\\\Log');
});

it('US-INFRA-020 — usa identifier oimpresso.silentFallback', function () {
    $src = readRule();
    expect($src)->toContain("->identifier('oimpresso.silentFallback')");
});

it('US-INFRA-020 — mensagem cita ADR 0212 e AP-18 (rastreabilidade canon)', function () {
    $src = readRule();
    expect($src)->toContain('ADR 0212');
    expect($src)->toContain('AP-18');
});

it('US-INFRA-020 — registrada em phpstan.neon.dist via services + tag', function () {
    $src = readPhpstanConfig();
    expect($src)->toContain('App\\PhpStan\\Rules\\NoSilentFallbackRule');
    expect($src)->toContain('phpstan.rules.rule');
});

it('US-INFRA-020 — baseline absorveu hits pre-existentes (>=10 ocorrências oimpresso.silentFallback)', function () {
    $baseline = readPhpstanBaseline();
    expect($baseline)->toContain('oimpresso.silentFallback');
    // Validação extra: ~100 esperados do projeto legacy UPOS (rule detectou 103)
    $count = substr_count($baseline, 'oimpresso.silentFallback');
    expect($count)->toBeGreaterThanOrEqual(10);
});
