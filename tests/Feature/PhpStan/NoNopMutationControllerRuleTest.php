<?php

declare(strict_types=1);

/**
 * Pest test estrutural — NoNopMutationControllerRule (ADR 0208 custom + T-AP-13, US-INFRA-019).
 *
 * Cobertura:
 *  1. Rule class existe + namespace App\PhpStan\Rules
 *  2. Implementa interface PHPStan\Rules\Rule
 *  3. Registrada em phpstan.neon.dist via services + tag
 *  4. getNodeType retorna ClassMethod::class
 *  5. READ_ONLY_METHODS lista index/show/create/edit (skip canon CRUD)
 *  6. Path filter Modules/* e app/Http/Controllers/*Controller.php
 *  7. Detecta back() + redirect()->back() + redirect()->route() + redirect()
 *  8. Identifier oimpresso.nopMutation
 *  9. Mensagem cita T-AP-13 LICOES_F3
 * 10. Baseline absorveu hits pre-existentes
 */

const NOP_RULE_PATH = 'app/PhpStan/Rules/NoNopMutationControllerRule.php';
const NOP_PHPSTAN_CONFIG = 'phpstan.neon.dist';
const NOP_BASELINE = 'phpstan-baseline.neon';

function readNopRule(): string
{
    return file_get_contents(base_path(NOP_RULE_PATH));
}

function readNopPhpstanConfig(): string
{
    return file_get_contents(base_path(NOP_PHPSTAN_CONFIG));
}

function readNopBaseline(): string
{
    return file_get_contents(base_path(NOP_BASELINE));
}

it('US-INFRA-019 — NoNopMutationControllerRule.php existe', function () {
    expect(file_exists(base_path(NOP_RULE_PATH)))->toBeTrue();
});

it('US-INFRA-019 — namespace App\\PhpStan\\Rules', function () {
    expect(readNopRule())->toContain('namespace App\\PhpStan\\Rules;');
});

it('US-INFRA-019 — implementa PHPStan\\Rules\\Rule interface', function () {
    $src = readNopRule();
    expect($src)->toContain('use PHPStan\\Rules\\Rule;');
    expect($src)->toMatch('/class\s+NoNopMutationControllerRule\s+implements\s+Rule/');
});

it('US-INFRA-019 — getNodeType retorna ClassMethod::class', function () {
    expect(readNopRule())->toContain('return ClassMethod::class;');
});

it('US-INFRA-019 — READ_ONLY_METHODS skip canon CRUD', function () {
    $src = readNopRule();
    foreach (['index', 'show', 'create', 'edit'] as $method) {
        expect($src)->toContain("'$method'");
    }
});

it('US-INFRA-019 — path filter Modules + app/Http/Controllers', function () {
    $src = readNopRule();
    expect($src)->toContain("'/Modules/'");
    expect($src)->toContain("'/app/Http/Controllers/'");
    expect($src)->toContain("'Controller.php'");
});

it('US-INFRA-019 — exige EXATAMENTE 1 statement no body', function () {
    $src = readNopRule();
    expect($src)->toContain('count($node->stmts) !== 1');
});

it('US-INFRA-019 — detecta back() helper direto', function () {
    $src = readNopRule();
    expect($src)->toMatch("/->toString\(\)\s*===\s*'back'/");
});

it('US-INFRA-019 — detecta redirect()->...() chain', function () {
    $src = readNopRule();
    expect($src)->toContain('MethodCall');
    expect($src)->toMatch("/->toString\(\)\s*===\s*'redirect'/");
});

it('US-INFRA-019 — usa identifier oimpresso.nopMutation', function () {
    expect(readNopRule())->toContain("->identifier('oimpresso.nopMutation')");
});

it('US-INFRA-019 — mensagem cita T-AP-13 LICOES_F3', function () {
    expect(readNopRule())->toContain('T-AP-13');
});

it('US-INFRA-019 — registrada em phpstan.neon.dist via services', function () {
    $src = readNopPhpstanConfig();
    expect($src)->toContain('App\\PhpStan\\Rules\\NoNopMutationControllerRule');
});

it('US-INFRA-019 — baseline absorveu hits pre-existentes (>=1)', function () {
    $baseline = readNopBaseline();
    expect($baseline)->toContain('oimpresso.nopMutation');
    $count = substr_count($baseline, 'oimpresso.nopMutation');
    expect($count)->toBeGreaterThanOrEqual(1);
});
