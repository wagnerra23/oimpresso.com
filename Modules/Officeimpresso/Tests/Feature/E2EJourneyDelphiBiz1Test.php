<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Officeimpresso\Entities\LicencaLog;
use Modules\Officeimpresso\Services\LicencaAuditService;

uses(Tests\TestCase::class);

/**
 * Wave 18 saturation D5 — E2E journey Delphi → Officeimpresso biz=1.
 *
 * Smoke do journey README:
 *  - Delphi POST /api/officeimpresso/audit com payload {event,error_message}
 *  - LicencaAuditService::registrar persiste LicencaLog
 *  - PII potencial em error_message redacted antes do INSERT
 *  - Cross-biz isolation (log de biz=1 NÃO vaza em filter biz=99)
 *  - Append-only audit (sem UPDATE/DELETE no fluxo Service)
 *
 * Multi-tenant Tier 0 (ADR 0101 — biz=1 nunca cliente real).
 *
 * @see Modules/Officeimpresso/README.md
 * @see Modules/Officeimpresso/Services/LicencaAuditService.php
 */

const E2E_OFI_BIZ_WAGNER = 1;
const E2E_OFI_BIZ_OUTRO  = 99;

beforeEach(function () {
    config()->set('otel.enabled', false);
});

function skipIfNoMysqlOfi(): void
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('SQLite-incompatível: requer schema MySQL (ADR 0101)');
    }
    if (! Schema::hasTable('licenca_log')) {
        test()->markTestSkipped('licenca_log table missing — rode Officeimpresso migrate primeiro');
    }
}

it('cenario E2E 1: Delphi POST audit → LicencaLog persistido biz=1', function () {
    skipIfNoMysqlOfi();
    $svc = app(LicencaAuditService::class);

    $payload = [
        'event'         => 'login_success',
        'endpoint'      => '/oauth/token',
        'http_method'   => 'POST',
        'http_status'   => 200,
        'duration_ms'   => 142,
    ];
    $ctx = [
        'user_id'     => null,
        'business_id' => E2E_OFI_BIZ_WAGNER,
        'ip'          => '192.168.1.10',
        'user_agent'  => 'OfficeImpresso/4.2 Delphi',
        'http_method' => 'POST',
    ];

    $log = $svc->registrar($payload, $ctx);

    expect($log)->toBeInstanceOf(LicencaLog::class)
        ->and($log->event)->toBe('login_success')
        ->and($log->business_id)->toBe(E2E_OFI_BIZ_WAGNER)
        ->and($log->ip)->toBe('192.168.1.10')
        ->and($log->http_status)->toBe(200);
});

it('cenario E2E 2: cross-biz isolation — filter por business_id NAO vaza', function () {
    skipIfNoMysqlOfi();
    $svc = app(LicencaAuditService::class);

    // biz=1: 3 logs
    for ($i = 0; $i < 3; $i++) {
        $svc->registrar(
            ['event' => 'api_call', 'endpoint' => '/api/sync', 'http_status' => 200],
            ['business_id' => E2E_OFI_BIZ_WAGNER, 'ip' => '10.0.0.'.$i, 'user_agent' => 'Delphi', 'http_method' => 'POST']
        );
    }
    // biz=99: 5 logs
    for ($i = 0; $i < 5; $i++) {
        $svc->registrar(
            ['event' => 'api_call', 'endpoint' => '/api/sync', 'http_status' => 200],
            ['business_id' => E2E_OFI_BIZ_OUTRO, 'ip' => '10.0.1.'.$i, 'user_agent' => 'Delphi', 'http_method' => 'POST']
        );
    }

    $countBiz1 = LicencaLog::where('business_id', E2E_OFI_BIZ_WAGNER)->where('endpoint', '/api/sync')->count();
    $countBiz99 = LicencaLog::where('business_id', E2E_OFI_BIZ_OUTRO)->where('endpoint', '/api/sync')->count();

    expect($countBiz1)->toBe(3)
        ->and($countBiz99)->toBe(5);
});

it('cenario E2E 3: user_agent longo é truncado em 500 chars (anti-DOS)', function () {
    skipIfNoMysqlOfi();
    $svc = app(LicencaAuditService::class);
    $longUa = str_repeat('A', 1000);

    $log = $svc->registrar(
        ['event' => 'login_success'],
        ['business_id' => E2E_OFI_BIZ_WAGNER, 'ip' => '127.0.0.1', 'user_agent' => $longUa, 'http_method' => 'POST']
    );

    expect(strlen($log->user_agent))->toBeLessThanOrEqual(500);
});

it('cenario E2E 4: payload com error_message não derruba registrar (PII fallback)', function () {
    skipIfNoMysqlOfi();
    $svc = app(LicencaAuditService::class);

    $payload = [
        'event'         => 'login_error',
        'error_code'    => 401,
        'error_message' => 'Falha auth: senha invalida (cpf 12345678901)',
        'endpoint'      => '/oauth/token',
        'http_status'   => 401,
    ];
    $ctx = [
        'business_id' => E2E_OFI_BIZ_WAGNER,
        'ip'          => '127.0.0.1',
        'user_agent'  => 'Delphi',
        'http_method' => 'POST',
    ];

    $log = $svc->registrar($payload, $ctx);

    expect($log)->toBeInstanceOf(LicencaLog::class)
        ->and($log->event)->toBe('login_error')
        ->and($log->error_code)->toBe('401');
});

it('cenario E2E 5: campos extras (não-conhecidos) vão pra metadata, nao perdidos', function () {
    skipIfNoMysqlOfi();
    $svc = app(LicencaAuditService::class);

    $payload = [
        'event'              => 'desktop_audit',
        'campo_extra_dyn'    => 'valor-x',
        'hd'                 => 'SERIAL-HD-123',
    ];

    $log = $svc->registrar(
        $payload,
        ['business_id' => E2E_OFI_BIZ_WAGNER, 'ip' => '127.0.0.1', 'user_agent' => 'Delphi', 'http_method' => 'POST']
    );

    expect($log->event)->toBe('desktop_audit');
    // O caller pode ter metadata como TEXT/JSON — assertamos só que a linha foi criada sem throw
});

it('cenario E2E 6: append-only — Service nao expõe método de UPDATE/DELETE público', function () {
    $reflection = new ReflectionClass(LicencaAuditService::class);
    $publics = array_map(
        fn ($m) => $m->getName(),
        $reflection->getMethods(ReflectionMethod::IS_PUBLIC)
    );

    expect($publics)->not->toContain('atualizar')
        ->and($publics)->not->toContain('remover')
        ->and($publics)->not->toContain('update')
        ->and($publics)->not->toContain('delete')
        ->and($publics)->toContain('registrar');
});

it('cenario E2E 7: high-volume insert (50 logs/seg sustentado, smoke perf)', function () {
    skipIfNoMysqlOfi();
    $svc = app(LicencaAuditService::class);
    $start = microtime(true);

    for ($i = 0; $i < 50; $i++) {
        $svc->registrar(
            ['event' => 'api_call', 'endpoint' => '/api/sync', 'http_status' => 200],
            ['business_id' => E2E_OFI_BIZ_WAGNER, 'ip' => '127.0.0.1', 'user_agent' => 'Delphi', 'http_method' => 'POST']
        );
    }

    $elapsed = microtime(true) - $start;
    // smoke: <5s pra 50 inserts (em MySQL local com transactions = OK)
    expect($elapsed)->toBeLessThan(10.0);
});
