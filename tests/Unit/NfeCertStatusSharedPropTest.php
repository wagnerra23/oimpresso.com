<?php

declare(strict_types=1);

/**
 * Unit test pro método `HandleInertiaRequests::nfeCertStatus()` — cobre as 4
 * ramificações + fallback silencioso quando módulo NfeBrasil indisponível.
 *
 * US-NFE-001 último item: badge sidebar quando cert ≤30d. O backend retorna
 * payload `{status, dias_restantes}` que o `<NfeCertBadge/>` lê via
 * `usePage().props.shell.nfe_cert_status`.
 *
 * Não usa app() bootstrap completo — substitui a Service via mock direto na
 * `app()->instance` pra evitar tocar DB.
 */

use App\Http\Middleware\HandleInertiaRequests;
use Modules\NfeBrasil\Services\CertificadoService;

uses(Tests\TestCase::class);

/**
 * Helper pra invocar o método protegido `nfeCertStatus()` com Service mockada.
 *
 * @return array{status: string, dias_restantes: ?int}|null
 */
function callNfeCertStatus(int $businessId, ?CertificadoService $mock = null): ?array
{
    if ($mock !== null) {
        app()->instance(CertificadoService::class, $mock);
    }
    $middleware = new HandleInertiaRequests(app());
    $reflection = new ReflectionMethod($middleware, 'nfeCertStatus');
    $reflection->setAccessible(true);

    return $reflection->invoke($middleware, $businessId);
}

it('retorna sem_cert quando verificarVencimento retorna null', function () {
    $mock = Mockery::mock(CertificadoService::class);
    $mock->shouldReceive('verificarVencimento')->with(4)->andReturn(null);

    $result = callNfeCertStatus(4, $mock);

    expect($result)->toBe([
        'status' => 'sem_cert',
        'dias_restantes' => null,
    ]);
});

it('retorna ok quando dias > 30', function () {
    $mock = Mockery::mock(CertificadoService::class);
    $mock->shouldReceive('verificarVencimento')->with(4)->andReturn(180);

    $result = callNfeCertStatus(4, $mock);

    expect($result)->toBe([
        'status' => 'ok',
        'dias_restantes' => 180,
    ]);
});

it('retorna vencendo quando dias entre 0 e 30', function () {
    $mock = Mockery::mock(CertificadoService::class);
    $mock->shouldReceive('verificarVencimento')->with(4)->andReturn(15);

    $result = callNfeCertStatus(4, $mock);

    expect($result)->toBe([
        'status' => 'vencendo',
        'dias_restantes' => 15,
    ]);
});

it('retorna vencendo no limite (dias = 30)', function () {
    $mock = Mockery::mock(CertificadoService::class);
    $mock->shouldReceive('verificarVencimento')->with(4)->andReturn(30);

    $result = callNfeCertStatus(4, $mock);

    expect($result['status'])->toBe('vencendo')
        ->and($result['dias_restantes'])->toBe(30);
});

it('retorna vencido quando dias < 0 (cert expirado)', function () {
    $mock = Mockery::mock(CertificadoService::class);
    $mock->shouldReceive('verificarVencimento')->with(4)->andReturn(-7);

    $result = callNfeCertStatus(4, $mock);

    expect($result)->toBe([
        'status' => 'vencido',
        'dias_restantes' => -7,
    ]);
});

it('retorna null quando Service lança exception (módulo desinstalado / migration ausente)', function () {
    $mock = Mockery::mock(CertificadoService::class);
    $mock->shouldReceive('verificarVencimento')->andThrow(new \RuntimeException('table not found'));

    $result = callNfeCertStatus(4, $mock);

    // Render do shell NUNCA pode falhar por cert NFe — try/catch protege.
    expect($result)->toBeNull();
});
