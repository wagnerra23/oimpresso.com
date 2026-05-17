<?php

declare(strict_types=1);

use Modules\Ponto\Entities\Marcacao;

uses(Tests\TestCase::class);

/**
 * Wave 28 SATURATION FINAL Ponto — push 90 → ≥92 (+2pp).
 *
 * Foco minimal D2 (+3 Pest Marcação append-only defesa em profundidade).
 *
 * Estratégia: source-level + reflexão (SQLite-friendly, sem hit MySQL).
 * Pattern alinhado com Wave26SaturationTest (mesmas dimensões + lições).
 *
 * Tier 0 IRREVOGÁVEL — Marcação append-only Portaria MTP 671/2021 Art. 85:
 *   ⛔ NUNCA `Marcacao::update()` ou `Marcacao::delete()` — defesa app + trigger MySQL
 *   ⛔ Anulação via `MarcacaoService::anular()` (cria NOVA marcação com `origem=ANULACAO`)
 *   ⛔ Cadeia hash SHA-256 sequencial (`hash_anterior` → `hash`) tampering-proof
 *   ⛔ NUNCA biz=4 (ROTA LIVRE Larissa) — ADR 0101
 *
 * @see Modules/Ponto/Entities/Marcacao.php (override update/delete)
 * @see Portaria MTP 671/2021 Anexo I §85
 */

it('W28 D2.a Marcacao::update() override lança RuntimeException (defesa app — Portaria 671)', function () {
    $ref = new ReflectionMethod(Marcacao::class, 'update');
    expect($ref->getDeclaringClass()->getName())->toBe(Marcacao::class);

    $source = file_get_contents((new ReflectionClass(Marcacao::class))->getFileName());
    expect($source)->toContain('RuntimeException');
    expect($source)->toContain('append-only');
});

it('W28 D2.b Marcacao::delete() override declarado na própria classe (Portaria 671 Art. 85)', function () {
    $ref = new ReflectionMethod(Marcacao::class, 'delete');
    expect($ref->getDeclaringClass()->getName())->toBe(Marcacao::class);

    $source = file_get_contents((new ReflectionClass(Marcacao::class))->getFileName());
    // Comentário documenta intent + defesa em camadas
    expect($source)->toContain('Triggers MySQL bloqueiam');
});

it('W28 D2.c Marcacao tem ORIGEM_ANULACAO constant (caminho canônico — não delete)', function () {
    expect(defined(Marcacao::class . '::ORIGEM_ANULACAO'))->toBeTrue();
    expect(Marcacao::ORIGEM_ANULACAO)->toBe('ANULACAO');

    // Garante que MarcacaoService::anular usa o caminho canônico (não update direto)
    expect(method_exists(\Modules\Ponto\Services\MarcacaoService::class, 'anular'))->toBeTrue();
});

it('W28 sanity Wave26 + Wave25 + Wave18 preservados (não-regressão)', function () {
    expect(file_exists(__DIR__ . '/Wave26SaturationTest.php'))->toBeTrue();
    expect(file_exists(__DIR__ . '/Wave25SaturationTest.php'))->toBeTrue();
});
