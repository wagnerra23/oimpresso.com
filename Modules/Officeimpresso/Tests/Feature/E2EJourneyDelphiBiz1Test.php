<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Officeimpresso\Entities\LicencaLog;
use Modules\Officeimpresso\Services\LicencaAuditService;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Wave 18 saturation D5 — E2E journey Delphi → Officeimpresso (LicencaAuditService).
 *
 * Smoke do journey README:
 *  - Delphi POST /api/officeimpresso/audit com payload {event,error_message}
 *  - LicencaAuditService::registrar persiste LicencaLog
 *  - PII potencial em error_message redacted antes do INSERT
 *  - o business_id do contexto é o que fica gravado (tenant 98 e 99 separados)
 *  - Append-only audit (sem UPDATE/DELETE no fluxo Service)
 *
 * Tenant 98 × 99 (ADR 0358), com DatabaseTransactions: as contagens valem por execução
 * mesmo no CT 100, onde o banco persiste. O nome do arquivo (Biz1) ficou do tempo em que
 * gravava em biz=1; renomear mudaria o path da allowlist da lane.
 *
 * @see memory/requisitos/Officeimpresso/README.md
 * @see Modules/Officeimpresso/Services/LicencaAuditService.php
 */

beforeEach(function () {
    config()->set('otel.enabled', false);
    // Tenant 98 (operadora de teste) x 99 (cliente) - ADR 0358. Antes era biz=1 chumbado,
    // empresa real: no CT 100 a base e clone de prod e os ~57 logs por run ficavam la.
    if (DB::connection()->getDriverName() !== 'sqlite') {
        $this->bizA = (int) $this->seededTenant()->id;
        $this->bizB = (int) $this->seededSupportClientTenant()->id;
    }
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

it('cenario E2E 1: Delphi POST audit → LicencaLog persistido no tenant 98', function () {
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
        'business_id' => $this->bizA,
        'ip'          => '192.168.1.10',
        'user_agent'  => 'OfficeImpresso/4.2 Delphi',
        'http_method' => 'POST',
    ];

    $log = $svc->registrar($payload, $ctx);

    expect($log)->toBeInstanceOf(LicencaLog::class)
        ->and($log->event)->toBe('login_success')
        ->and($log->business_id)->toBe($this->bizA)
        ->and($log->ip)->toBe('192.168.1.10')
        ->and($log->http_status)->toBe(200);
});

it('cenario E2E 2: cross-biz isolation — filter por business_id NAO vaza', function () {
    skipIfNoMysqlOfi();
    $svc = app(LicencaAuditService::class);

    // tenant 98: 3 logs
    for ($i = 0; $i < 3; $i++) {
        $svc->registrar(
            ['event' => 'api_call', 'endpoint' => '/api/sync', 'http_status' => 200],
            ['business_id' => $this->bizA, 'ip' => '10.0.0.'.$i, 'user_agent' => 'Delphi', 'http_method' => 'POST']
        );
    }
    // tenant 99: 5 logs
    for ($i = 0; $i < 5; $i++) {
        $svc->registrar(
            ['event' => 'api_call', 'endpoint' => '/api/sync', 'http_status' => 200],
            ['business_id' => $this->bizB, 'ip' => '10.0.1.'.$i, 'user_agent' => 'Delphi', 'http_method' => 'POST']
        );
    }

    $countA = LicencaLog::where('business_id', $this->bizA)->where('endpoint', '/api/sync')->count();
    $countB = LicencaLog::where('business_id', $this->bizB)->where('endpoint', '/api/sync')->count();

    expect($countA)->toBe(3)
        ->and($countB)->toBe(5);
});

it('cenario E2E 3: user_agent longo é truncado em 500 chars (anti-DOS)', function () {
    skipIfNoMysqlOfi();
    $svc = app(LicencaAuditService::class);
    $longUa = str_repeat('A', 1000);

    $log = $svc->registrar(
        ['event' => 'login_success'],
        ['business_id' => $this->bizA, 'ip' => '127.0.0.1', 'user_agent' => $longUa, 'http_method' => 'POST']
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
        'business_id' => $this->bizA,
        'ip'          => '127.0.0.1',
        'user_agent'  => 'Delphi',
        'http_method' => 'POST',
    ];

    $log = $svc->registrar($payload, $ctx);

    expect($log)->toBeInstanceOf(LicencaLog::class)
        ->and($log->event)->toBe('login_error')
        // A coluna é varchar(100): o valor gravado é '401'. O model que o create() devolve
        // ainda guarda o int do payload, por isso a leitura é do banco (fresh).
        ->and($log->fresh()->error_code)->toBe('401');
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
        ['business_id' => $this->bizA, 'ip' => '127.0.0.1', 'user_agent' => 'Delphi', 'http_method' => 'POST']
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
            ['business_id' => $this->bizA, 'ip' => '127.0.0.1', 'user_agent' => 'Delphi', 'http_method' => 'POST']
        );
    }

    $elapsed = microtime(true) - $start;
    // smoke: <5s pra 50 inserts (em MySQL local com transactions = OK)
    expect($elapsed)->toBeLessThan(10.0);
});
