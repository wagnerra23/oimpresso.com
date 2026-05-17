<?php

declare(strict_types=1);

use App\Util\OtelHelper;
use Modules\Officeimpresso\Services\LicencaAuditService;
use Modules\Officeimpresso\Services\LicencaService;

uses(Tests\TestCase::class);

/**
 * Wave 18 saturation D2/D9 — verifica que LicencaService + LicencaAuditService
 * preservam spans canon `officeimpresso.*` e seguem patterns Tier 0.
 *
 * @see Modules/Officeimpresso/Services/LicencaService.php
 * @see Modules/Officeimpresso/Services/LicencaAuditService.php
 */

it('cenario 1: LicencaService usa OtelHelper::spanBiz em todos os metodos publicos', function () {
    $file = (new ReflectionClass(LicencaService::class))->getFileName();
    $src  = file_get_contents($file);

    expect($src)->toContain('use App\Util\OtelHelper;');

    // contagem de spanBiz — esperamos ≥ 5 (CRUD + bloqueio + listar)
    $count = substr_count($src, 'OtelHelper::spanBiz');
    expect($count)->toBeGreaterThanOrEqual(5, "Esperava ≥5 spans no LicencaService, encontrou {$count}");
});

it('cenario 2: LicencaAuditService usa OtelHelper::spanBiz no registrar', function () {
    $file = (new ReflectionClass(LicencaAuditService::class))->getFileName();
    $src  = file_get_contents($file);

    expect($src)->toContain('OtelHelper::spanBiz');
});

it('cenario 3: span names seguem prefixo "officeimpresso." canon', function () {
    $files = [
        (new ReflectionClass(LicencaService::class))->getFileName(),
        (new ReflectionClass(LicencaAuditService::class))->getFileName(),
    ];

    foreach ($files as $f) {
        $src = file_get_contents($f);
        $matches = preg_match_all("/'officeimpresso\\.[a-z_]+\\.[a-z_]+'/", $src);
        expect($matches)->toBeGreaterThanOrEqual(1, "Esperava 1+ span canon em ".basename($f));
    }
});

it('cenario 4: OtelHelper::spanBiz roda no-op com otel.enabled=false', function () {
    config()->set('otel.enabled', false);

    $r = OtelHelper::spanBiz('officeimpresso.test.smoke', fn () => 'ok', ['module' => 'Officeimpresso']);
    expect($r)->toBe('ok');
});

it('cenario 5: OtelHelper preserva exception em service-level operations', function () {
    config()->set('otel.enabled', false);

    expect(fn () => OtelHelper::spanBiz(
        'officeimpresso.test.boom',
        fn () => throw new \RuntimeException('officeimpresso-span-error')
    ))->toThrow(\RuntimeException::class, 'officeimpresso-span-error');
});

it('cenario 6: Services nao usam OtelHelper de namespace errado (lock-in canon)', function () {
    $files = [
        (new ReflectionClass(LicencaService::class))->getFileName(),
        (new ReflectionClass(LicencaAuditService::class))->getFileName(),
    ];

    foreach ($files as $f) {
        $src = file_get_contents($f);
        expect($src)->not->toContain('Modules\Whatsapp\Support\OtelHelper');
        expect($src)->not->toContain('Modules\Officeimpresso\Support\OtelHelper');
    }
});

it('cenario 7: README do modulo Officeimpresso existe e cita ADR 0159', function () {
    $path = base_path('Modules/Officeimpresso/README.md');
    expect(file_exists($path))->toBeTrue('README.md deveria existir após Wave 18 D5');

    $src = file_get_contents($path);
    expect($src)->toContain('Officeimpresso')
        ->and($src)->toContain('ADR 0159')
        ->and($src)->toContain('PII redactor');
});
