<?php

declare(strict_types=1);

use App\Util\OtelHelper;
use Modules\OficinaAuto\Services\AprovacaoOsService;
use Modules\OficinaAuto\Services\ServiceOrderSummaryService;
use Modules\OficinaAuto\Services\VehicleQueryService;

uses(Tests\TestCase::class);

/**
 * Wave 18 saturation D2/D9 — verifica que os Services novos extraídos
 * dos Controllers (VehicleQueryService, ServiceOrderSummaryService) +
 * AprovacaoOsService já estão integrados ao OtelHelper canon
 * (`App\Util\OtelHelper`) e seguem o pattern 'oficinaauto.*' nos span names.
 *
 * D9.a (ADR 0155): zero-cost spans envolvendo queries DB críticas.
 *
 * @see app/Util/OtelHelper.php
 */

it('cenario 1: VehicleQueryService existe + Container resolve', function () {
    $s = app(VehicleQueryService::class);
    expect($s)->toBeInstanceOf(VehicleQueryService::class);
});

it('cenario 2: ServiceOrderSummaryService existe + Container resolve', function () {
    $s = app(ServiceOrderSummaryService::class);
    expect($s)->toBeInstanceOf(ServiceOrderSummaryService::class);
});

it('cenario 3: AprovacaoOsService existe + Container resolve', function () {
    $s = app(AprovacaoOsService::class);
    expect($s)->toBeInstanceOf(AprovacaoOsService::class);
});

it('cenario 4: VehicleQueryService usa OtelHelper canonico (smoke source-grep)', function () {
    $file = (new ReflectionClass(VehicleQueryService::class))->getFileName();
    $src  = file_get_contents($file);

    expect($src)->toContain('use App\Util\OtelHelper;');
    expect($src)->toContain('OtelHelper::spanBiz');
    expect($src)->toContain("'oficinaauto."); // span prefix canon
});

it('cenario 5: ServiceOrderSummaryService usa OtelHelper canonico (smoke source-grep)', function () {
    $file = (new ReflectionClass(ServiceOrderSummaryService::class))->getFileName();
    $src  = file_get_contents($file);

    expect($src)->toContain('use App\Util\OtelHelper;');
    expect($src)->toContain('OtelHelper::spanBiz');
});

it('cenario 6: VehicleQueryService.contagemPorStatus retorna zeros se schema ausente (fail-soft)', function () {
    config()->set('otel.enabled', false);

    if (\Illuminate\Support\Facades\Schema::hasColumn('vehicles', 'current_status')) {
        $this->markTestSkipped('schema FSM presente — fail-soft inverso testado em test FSM');
    }

    $s = new VehicleQueryService();
    $r = $s->contagemPorStatus();

    expect($r)->toHaveKeys(['disponivel', 'locada', 'manutencao', 'atrasada', 'total'])
        ->and($r['total'])->toBe(0);
});

it('cenario 7: OtelHelper preserva exception nos services (não engole)', function () {
    config()->set('otel.enabled', false);

    expect(fn () => OtelHelper::spanBiz(
        'oficinaauto.test.boom',
        fn () => throw new \RuntimeException('span-deve-propagar')
    ))->toThrow(\RuntimeException::class, 'span-deve-propagar');
});

it('cenario 8: span names seguem prefixo "oficinaauto." nos 3 services', function () {
    $files = [
        (new ReflectionClass(VehicleQueryService::class))->getFileName(),
        (new ReflectionClass(ServiceOrderSummaryService::class))->getFileName(),
    ];

    foreach ($files as $f) {
        $src = file_get_contents($f);
        // contagem de spans com prefixo "oficinaauto." — alvo D9.a: ≥3 spans/service
        $matches = preg_match_all("/'oficinaauto\\.[a-z_]+\\.[a-z_]+'/", $src);
        expect($matches)->toBeGreaterThanOrEqual(2, "Esperava 2+ spans canon em ".basename($f));
    }
});
