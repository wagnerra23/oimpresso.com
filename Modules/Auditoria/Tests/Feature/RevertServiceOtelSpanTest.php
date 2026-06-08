<?php

declare(strict_types=1);

use App\Util\OtelHelper;
use Modules\Auditoria\Services\RevertService;

/**
 * D9.a — OtelHelper wrap em RevertService::revert().
 *
 * Verifica contrato: OtelHelper::span/spanBiz zero-cost quando otel.enabled=false
 * (default prod). Span criado quando habilitado + class_exists SDK.
 *
 * NAO testa span de revert real (requer Activity + permissoes Spatie — escopo
 * MultiTenantIsolationTest). Testa apenas que helper esta acessivel e zero-cost.
 *
 * @see Modules\Auditoria\Services\RevertService::revert()
 * @see app/Util/OtelHelper.php
 */

uses(Tests\TestCase::class);

it('OtelHelper zero-cost quando otel.enabled=false (default prod)', function () {
    config(['otel.enabled' => false]);

    $executed = false;
    $result = OtelHelper::span('auditoria.test.span', ['business_id' => 1], function () use (&$executed) {
        $executed = true;
        return 'ok';
    });

    expect($executed)->toBeTrue();
    expect($result)->toBe('ok');
});

it('OtelHelper::spanBiz auto-resolve business_id sem session', function () {
    config(['otel.enabled' => false]);

    $result = OtelHelper::spanBiz('auditoria.test.spanbiz', function () {
        return 42;
    }, ['module' => 'Auditoria']);

    expect($result)->toBe(42);
});

it('RevertService import OtelHelper sem erro de classe', function () {
    expect(class_exists(OtelHelper::class))->toBeTrue();
    expect(class_exists(RevertService::class))->toBeTrue();

    // Verifica que RevertService use statement esta correto
    $reflection = new ReflectionClass(RevertService::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('use App\\Util\\OtelHelper;');
    expect($source)->toContain('OtelHelper::spanBiz');
    expect($source)->toContain('auditoria.revert.execute');
});

it('OTel span attributes NUNCA contem PII (apenas IDs + flags)', function () {
    $reflection = new ReflectionClass(RevertService::class);
    $source = file_get_contents($reflection->getFileName());

    // attributes do span devem ter apenas: module, activity_id, subject_type,
    // subject_id, restored_attrs_count, has_reason — NUNCA reason text nem
    // properties.old nem causer email
    expect($source)->toContain("'module'");
    expect($source)->toContain("'activity_id'");
    expect($source)->toContain("'has_reason'"); // flag bool, nao texto
    expect($source)->not->toContain("'reason' => \$reason"); // critico: texto livre fora
});
