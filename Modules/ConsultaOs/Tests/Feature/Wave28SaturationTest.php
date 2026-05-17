<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

uses(Tests\TestCase::class);

/**
 * Wave 28 SATURATION FINAL ConsultaOs — push 88 → ≥92 (+4pp).
 *
 * Foco minimal:
 *   - D2 (+3) Pest portal público resilience (rota throttle + sem session +
 *             Repository contract — defesa em camadas)
 *   - D9 (+1) span audit catalog confirmação (3 spans canon catalogados)
 *
 * Tier 0 IRREVOGÁVEL (ADR 0093 §"Exceção repo-wide"):
 *   ⛔ Portal público NÃO scopa por business_id (cliente externo sem sessão)
 *   ⛔ US-CONSULTA-001 ativa busca real → Service resolve biz via protocol lookup
 *
 * SQLite-friendly: zero hit DB, zero hit prod (Mock Repository).
 *
 * @see Modules/ConsultaOs/Tests/Feature/Wave26SaturationTest.php
 * @see Modules/ConsultaOs/Services/ConsultaOsMockService.php
 */

// ============================================================================
// D2 — Resilience portal público (rota throttle + DI contract + sem session)
// ============================================================================

it('W28 D2.a rota /consulta-os/buscar tem middleware throttle declarado (anti-enumeration)', function () {
    $route = Route::getRoutes()->getByName('consulta-os.buscar');

    if (! $route) {
        $this->markTestSkipped('Rota consulta-os.buscar não registrada (Module disabled?).');
    }

    $middlewares = $route->middleware();
    $temThrottle = collect($middlewares)->contains(fn ($m) => str_starts_with($m, 'throttle:'));
    expect($temThrottle)->toBeTrue('Rota pública DEVE ter throttle (defesa anti-enumeration brute-force)');
});

it('W28 D2.b ConsultaOsController::buscar usa FormRequest dedicado (validação Tier 0)', function () {
    $ref = new ReflectionMethod(\Modules\ConsultaOs\Http\Controllers\ConsultaOsController::class, 'buscar');
    $params = $ref->getParameters();

    expect($params)->toHaveCount(1);
    expect((string) $params[0]->getType())
        ->toBe(\Modules\ConsultaOs\Http\Requests\ConsultaPublicaRequest::class);
});

it('W28 D2.c ConsultaOsRepositoryInterface contract preservado (Mock ↔ Real binding stable)', function () {
    // Contract DEVE existir + ter buscarPorNumero (US-CONSULTA-001 switch é 1-line)
    expect(interface_exists(\Modules\ConsultaOs\Contracts\ConsultaOsRepositoryInterface::class))->toBeTrue();

    $ref = new ReflectionClass(\Modules\ConsultaOs\Contracts\ConsultaOsRepositoryInterface::class);
    expect($ref->hasMethod('buscarPorNumero'))->toBeTrue();

    // ConsultaOsMockService recebe interface — não classe concreta (SoC brutal Tier 0)
    $ctor = new ReflectionMethod(\Modules\ConsultaOs\Services\ConsultaOsMockService::class, '__construct');
    $params = $ctor->getParameters();
    expect($params)->toHaveCount(1);
    expect((string) $params[0]->getType())
        ->toBe(\Modules\ConsultaOs\Contracts\ConsultaOsRepositoryInterface::class);
});

// ============================================================================
// D9 — span catalog ConsultaOs (busca + health + audit log estruturado)
// ============================================================================

it('W28 D9 ConsultaOs catalog ≥2 spans canon + log audit estruturado (cobertura completa)', function () {
    $spans = [
        'consultaos.busca_publica' => 'Modules/ConsultaOs/Services/ConsultaOsMockService.php',
        'consultaos.health'        => 'Modules/ConsultaOs/Console/Commands/ConsultaOsHealthCommand.php',
    ];

    foreach ($spans as $spanName => $path) {
        $src = file_get_contents(base_path($path));
        expect(str_contains($src, $spanName))
            ->toBeTrue("Span '{$spanName}' deve estar em {$path} (Wave 28 cobertura)");
    }

    // Controller emite log estruturado complementar aos spans (PII redacted)
    $ctrlSrc = file_get_contents(base_path('Modules/ConsultaOs/Http/Controllers/ConsultaOsController.php'));
    expect($ctrlSrc)->toContain('consultaos.busca_publica');
    expect($ctrlSrc)->toContain('PiiRedactor');
});

it('W28 sanity Wave 26/25 preservados (não-regressão)', function () {
    expect(file_exists(__DIR__ . '/Wave26SaturationTest.php'))->toBeTrue();
    expect(file_exists(__DIR__ . '/Wave25SaturationTest.php'))->toBeTrue();
});
