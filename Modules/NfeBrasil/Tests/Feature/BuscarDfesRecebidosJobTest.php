<?php

declare(strict_types=1);

use Modules\NfeBrasil\Jobs\BuscarDfesRecebidosJob;
use Modules\NfeBrasil\Services\Manifestacao\DistribuicaoDfeService;

uses(Tests\TestCase::class);

/**
 * US-NFE-051 — BuscarDfesRecebidosJob.
 *
 * Job é fino (delega ao service). Testa apenas que repassa o businessId.
 */

it('handle delega ao DistribuicaoDfeService::puxarLote com businessId', function () {
    $businessId = 1;

    $serviceMock = \Mockery::mock(DistribuicaoDfeService::class);
    $serviceMock->shouldReceive('puxarLote')
        ->once()
        ->with($businessId)
        ->andReturn(['processados' => 0, 'last_nsu' => 0]);

    $job = new BuscarDfesRecebidosJob($businessId);
    $job->handle($serviceMock);

    expect(true)->toBeTrue(); // mock expectations validam acima

    \Mockery::close();
});

it('expõe tags multi-tenant pra horizon dashboard', function () {
    $job = new BuscarDfesRecebidosJob(99);
    $tags = $job->tags();

    expect($tags)->toContain('nfebrasil');
    expect($tags)->toContain('dist-dfe');
    expect($tags)->toContain('biz:99');
});

it('businessId é readonly', function () {
    $job = new BuscarDfesRecebidosJob(7);
    expect($job->businessId)->toBe(7);
});
