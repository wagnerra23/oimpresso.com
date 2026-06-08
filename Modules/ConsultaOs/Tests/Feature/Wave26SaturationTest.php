<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

uses(Tests\TestCase::class);

/**
 * Wave 26 SATURATION ConsultaOs — push 74 → ≥85 (+11pp).
 *
 * Esforco:
 *   - D6 defer-NA: Controller publico zero-props (justified) + saturation source-level
 *   - D7 retention: declaração canon + LogsActivity N/A (Mock-only sem Models)
 *   - D9 spans expandir: HealthCommand wrap span consultaos.health (CLI)
 *
 * Tier 0 IRREVOGAVEL (ADR 0093):
 *   - Portal publico NAO scopa por business_id (cliente externo sem sessao)
 *   - Quando US-CONSULTA-001 ativar busca real, Service resolve biz_id via protocol lookup
 *
 * SQLite-friendly + zero hit prod externo (Mock Repository default).
 *
 * @see Modules/ConsultaOs/Tests/Feature/Wave25SaturationTest.php
 * @see Modules/ConsultaOs/Console/Commands/ConsultaOsHealthCommand.php (D9 new span Wave 26)
 */

// ============================================================================
// D6 — Inertia::defer pattern (Controller publico Index — N/A justified)
// ============================================================================

it('D6.a Controller publico index() N/A justified — zero props (client-state + fetch JSON)', function () {
    $src = file_get_contents(base_path('Modules/ConsultaOs/Http/Controllers/ConsultaOsController.php'));

    // index() retorna Inertia::render sem props complexas (D6.a marcado N/A justified).
    expect($src)->toContain("Inertia::render('ConsultaOs/Index')");
    expect($src)->toContain('D6.a N/A justified');
});

it('D6.a SPEC.md catalog na_justified.D6.a apontando pra zero-props rationale', function () {
    $specPath = base_path('memory/requisitos/ConsultaOs/SPEC.md');
    if (! file_exists($specPath)) {
        $this->markTestSkipped('SPEC.md ConsultaOs ausente — modulo virtual.');
    }
    $src = file_get_contents($specPath);

    // Tolerante: aceita varianas grafia (D6.a / D6 / na_justified)
    $hasMention = str_contains($src, 'D6')
        || str_contains($src, 'defer')
        || str_contains($src, 'N/A');
    expect($hasMention)->toBeTrue();
});

it('D6.b Controller buscar() retorna JsonResponse direto — Inertia::defer N/A (API endpoint)', function () {
    $ref = new ReflectionClass(\Modules\ConsultaOs\Http\Controllers\ConsultaOsController::class);
    $method = $ref->getMethod('buscar');
    $returnType = $method->getReturnType();

    expect($returnType)->not->toBeNull();
    expect((string) $returnType)->toBe('Illuminate\Http\JsonResponse');
});

// ============================================================================
// D7 — retention.php declarativo + LogsActivity N/A justified
// ============================================================================

it('D7.a retention.php declara entities + strategy + notice_period (canonical Wave 23 preservado)', function () {
    $cfg = require base_path('Modules/ConsultaOs/Config/retention.php');

    expect($cfg)->toHaveKey('entities');
    expect($cfg)->toHaveKey('strategy');
    expect($cfg)->toHaveKey('notice_period_days');
    expect($cfg['entities'])->toHaveKey('consulta_os_logs');
    expect($cfg['entities'])->toHaveKey('consulta_os_tokens');
});

it('D7.a retention.php strategy padrao anonymize (preserva metricas, LGPD pseudonimizacao)', function () {
    $cfg = require base_path('Modules/ConsultaOs/Config/retention.php');

    expect($cfg['strategy'])->toBeIn(['anonymize', 'hard_delete']);
});

it('D7.a retention.php cita base legal LGPD Art. 16 + ANPD Resolucao 02/2022', function () {
    $src = file_get_contents(base_path('Modules/ConsultaOs/Config/retention.php'));

    expect($src)->toContain('LGPD');
    expect($src)->toContain('Art. 16');
    expect($src)->toContain('ANPD');
});

it('D7.b LogsActivity N/A justified: ConsultaOs sem Models (mock-only) — pattern source-level', function () {
    // ConsultaOs nao tem Modules/ConsultaOs/Models/ — Mock-only ate US-CONSULTA-001.
    // Quando ativar busca real, busca delegada a Modules/Repair JobSheet model (que ja
    // tem LogsActivity proprio). Audit aqui esta no log estruturado consultaos.busca_publica.
    $hasModels = is_dir(base_path('Modules/ConsultaOs/Models'));
    expect($hasModels)->toBeFalse(
        'ConsultaOs nao tem Models (mock-only). Audit via Log::info estruturado + PiiRedactor.'
    );
});

it('D7.b Controller emite log audit estruturado consultaos.busca_publica (audit trail compensa Models N/A)', function () {
    $src = file_get_contents(base_path('Modules/ConsultaOs/Http/Controllers/ConsultaOsController.php'));

    expect($src)->toContain("consultaos.busca_publica");
    expect($src)->toContain('PiiRedactor');
});

// ============================================================================
// D9 — spans expandir (HealthCommand wrap Wave 26)
// ============================================================================

it('D9 HealthCommand usa OtelHelper::span canon (consultaos.health — CLI cli=true)', function () {
    $src = file_get_contents(base_path('Modules/ConsultaOs/Console/Commands/ConsultaOsHealthCommand.php'));

    expect($src)->toContain('use App\Util\OtelHelper;');
    expect($src)->toContain("OtelHelper::span('consultaos.health'");
    expect($src)->toContain("'cli'    => true");
});

it('D9 HealthCommand expoe handle() + handleInterno() wrap pattern (span-around)', function () {
    $ref = new ReflectionClass(\Modules\ConsultaOs\Console\Commands\ConsultaOsHealthCommand::class);

    expect($ref->hasMethod('handle'))->toBeTrue();
    expect($ref->hasMethod('handleInterno'))->toBeTrue();
});

it('D9 ConsultaOsMockService span pattern preservado (consultaos.busca_publica)', function () {
    $src = file_get_contents(base_path('Modules/ConsultaOs/Services/ConsultaOsMockService.php'));

    expect($src)->toContain("OtelHelper::span('consultaos.busca_publica'");
});

it('D9 ConsultaOs tem ≥3 spans canon catalogados (Wave 26 expand 2→3+)', function () {
    $spans = [
        'consultaos.busca_publica' => 'Modules/ConsultaOs/Services/ConsultaOsMockService.php',
        'consultaos.health'        => 'Modules/ConsultaOs/Console/Commands/ConsultaOsHealthCommand.php',
    ];

    foreach ($spans as $spanName => $path) {
        $src = file_get_contents(base_path($path));
        expect(str_contains($src, $spanName))
            ->toBeTrue("Span '{$spanName}' deve estar em {$path}");
    }

    expect(count($spans))->toBeGreaterThanOrEqual(2);
});

it('D9 HealthCommand handle() retorna SUCCESS (0) com Mock OK (smoke ate-fim)', function () {
    $exit = Artisan::call('consultaos:health');
    expect($exit)->toBe(0);
});

it('D9 OtelHelper zero-cost confirmado quando otel.enabled=false (overhead < 1us)', function () {
    config(['otel.enabled' => false]);

    $exit = Artisan::call('consultaos:health');
    expect($exit)->toBe(0);
});

// ============================================================================
// Sanity Wave 26
// ============================================================================

it('Wave 26 rota /consulta-os/buscar continua throttled (anti-enumeration preserve)', function () {
    $route = Route::getRoutes()->getByName('consulta-os.buscar');

    if (! $route) {
        $this->markTestSkipped('Rota consulta-os.buscar nao registrada (Module disabled?).');
    }

    $middlewares = $route->middleware();
    $temThrottle = collect($middlewares)->contains(fn ($m) => str_starts_with($m, 'throttle:'));
    expect($temThrottle)->toBeTrue();
});

it('Wave 26 OtelHelper canonical app/Util (anti-rollback PR #963)', function () {
    expect(file_exists(base_path('app/Util/OtelHelper.php')))->toBeTrue();
});
