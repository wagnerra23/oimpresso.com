<?php

declare(strict_types=1);

use Modules\Brief\Services\BriefValidator;
use Modules\Brief\Services\ValidationResult;

uses(Tests\TestCase::class);

/**
 * BriefValidatorTest — Wave 18 D2 SATURATION.
 *
 * Cobertura de unidade pros 4 invariantes ADR 0091 (BriefValidator):
 *   1. 7 headers exatos na ordem correta
 *   2. Termina com \n---END---
 *   3. Token count ≤ 3500 (~4 chars/token PT-BR)
 *   4. Sem PII de cliente final (CPF/CNPJ)
 *
 * Multi-tenant Tier 0: brief é repo-wide. Sem business_id — apenas regex puras.
 *
 * @see Modules\Brief\Services\BriefValidator
 * @see Modules\Brief\Services\ValidationResult
 * @see memory/decisions/0091-daily-brief.md
 */

/** Builder de brief sintético válido. */
function briefValido(): string
{
    return implode("\n\n", [
        '## ESTADO MACRO',
        'Texto do estado macro.',
        '## EM VOO AGORA',
        'Nada em voo no momento.',
        '## DECISÕES RECENTES (24h)',
        'Nenhuma decisão.',
        '## SKILLS USO 7d',
        'brief-first usado 12x.',
        '## CHARTERS APODRECENDO',
        'Lista vazia.',
        '## FLAGS',
        'Sem flags pendentes.',
        '## METADATA',
        'Gerado em test.',
        '---END---',
    ]);
}

it('valida brief com 7 headers ordenados + sentinela + tokens ok', function () {
    $v = new BriefValidator();
    $r = $v->validate(briefValido());

    expect($r)->toBeInstanceOf(ValidationResult::class);
    expect($r->isOk())->toBeTrue();
    expect($r->tokenCount)->toBeGreaterThan(0);
    expect($r->reason)->toBe('');
});

it('falha quando 1º header está ausente', function () {
    $brief = str_replace('## ESTADO MACRO', '## OUTRO HEADER', briefValido());
    $r = (new BriefValidator())->validate($brief);

    expect($r->isOk())->toBeFalse();
    expect($r->reason)->toContain('missing_or_misordered');
});

it('falha quando headers fora de ordem', function () {
    $valid = briefValido();
    // Troca ESTADO MACRO ↔ EM VOO AGORA (swap blocos)
    $swap = str_replace(
        ['## ESTADO MACRO', '## EM VOO AGORA'],
        ['__PLACEHOLDER_A__', '__PLACEHOLDER_B__'],
        $valid
    );
    $swap = str_replace(
        ['__PLACEHOLDER_A__', '__PLACEHOLDER_B__'],
        ['## EM VOO AGORA', '## ESTADO MACRO'],
        $swap
    );
    $r = (new BriefValidator())->validate($swap);

    expect($r->isOk())->toBeFalse();
    expect($r->reason)->toContain('missing_or_misordered');
});

it('falha quando sentinela ---END--- ausente', function () {
    $brief = str_replace('---END---', '', briefValido());
    $r = (new BriefValidator())->validate($brief);

    expect($r->isOk())->toBeFalse();
    expect($r->reason)->toBe('missing_end_sentinel');
});

it('falha quando token count > 3500', function () {
    // Brief válido + ~15k chars de padding (>3500 tokens estimados)
    $valid = briefValido();
    $padded = str_replace('---END---', str_repeat('a', 15_000) . "\n---END---", $valid);
    $r = (new BriefValidator())->validate($padded);

    expect($r->isOk())->toBeFalse();
    expect($r->reason)->toContain('token_overflow');
});

it('falha quando CPF de cliente final aparece (LGPD)', function () {
    $brief = str_replace('## METADATA', "## METADATA\nCPF: 123.456.789-00 vazou.", briefValido());
    $r = (new BriefValidator())->validate($brief);

    expect($r->isOk())->toBeFalse();
    expect($r->reason)->toBe('pii_leaked');
});

it('falha quando CNPJ de cliente final aparece (LGPD)', function () {
    $brief = str_replace('## METADATA', "## METADATA\nCNPJ: 12.345.678/0001-90 vazou.", briefValido());
    $r = (new BriefValidator())->validate($brief);

    expect($r->isOk())->toBeFalse();
    expect($r->reason)->toBe('pii_leaked');
});

it('ValidationResult::ok cria instância imutável', function () {
    $r = ValidationResult::ok(1234);

    expect($r->isOk())->toBeTrue();
    expect($r->tokenCount)->toBe(1234);
    expect($r->reason)->toBe('');
});

it('ValidationResult::fail cria instância imutável com reason', function () {
    $r = ValidationResult::fail('test_reason_xyz');

    expect($r->isOk())->toBeFalse();
    expect($r->tokenCount)->toBe(0);
    expect($r->reason)->toBe('test_reason_xyz');
});

it('BriefValidator::REQUIRED_HEADERS bate exato 7 entradas (ADR 0091)', function () {
    expect(BriefValidator::REQUIRED_HEADERS)->toHaveCount(7);
    expect(BriefValidator::MAX_TOKENS)->toBe(3500);
});
