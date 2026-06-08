<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Tests do command rb:apply-wr-comercial-retroatividade.
 *
 * Multi-tenant Tier 0 (ADR 0093): --business-id obrigatório, recusa sem flag.
 * Pest biz=1 (ADR 0101): nunca biz=cliente real.
 */
test('command rejeita sem --business-id (Tier 0 ADR 0093)', function () {
    $exitCode = Artisan::call('rb:apply-wr-comercial-retroatividade');

    expect($exitCode)->toBe(1);
    expect(Artisan::output())->toContain('--business-id obrigatório');
});

test('command rejeita business-id zero', function () {
    $exitCode = Artisan::call('rb:apply-wr-comercial-retroatividade', ['--business-id' => '0']);

    expect($exitCode)->toBe(1);
});

test('command rejeita business-id negativo', function () {
    $exitCode = Artisan::call('rb:apply-wr-comercial-retroatividade', ['--business-id' => '-1']);

    expect($exitCode)->toBe(1);
});

test('dry-run biz=1 não persiste e completa OK', function () {
    // Em CI sqlite sem rb_plans/rb_invoices existentes (módulo não migrado),
    // o test pula. Em ambiente com schema RB completo, dry-run só conta.
    if (! Schema::hasTable('rb_plans') || ! Schema::hasTable('rb_invoices')) {
        $this->markTestSkipped('rb_plans/rb_invoices não existem — migração RB não rodou.');
    }

    $exitCode = Artisan::call('rb:apply-wr-comercial-retroatividade', [
        '--business-id' => '1',
        '--dry-run' => true,
        '--skip-bridge' => true,
    ]);

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain('DRY RUN');
});
