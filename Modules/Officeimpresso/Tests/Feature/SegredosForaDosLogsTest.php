<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Officeimpresso\Entities\LicencaLog;
use Modules\Officeimpresso\Http\Middleware\LogDelphiAccess;
use Symfony\Component\HttpFoundation\Response;

uses(Tests\TestCase::class);

/**
 * Credencial do desktop fora de `licenca_log` (auditoria Officeimpresso 2026-11-05, eixo 5: A1, M4).
 *
 * `LogDelphiAccess` gravava até 4 KB do corpo cru e o header `X-API-Key`; o PiiRedactor não cobre
 * senha, contra-senha, serial nem token.
 *
 * Fora daqui (M2/M3, ActivityLog das entidades): tocar `Licenca_Computador`/`LicencaLog` acorda a
 * dívida de escopo por empresa (MultiTenantScopeArchitectureTest), que é decisão própria.
 *
 * Os casos passam pelo `handle()` do middleware, não por um helper, e cada um tem âncora positiva
 * (a linha do log existe, o atributo comum aparece) para não ficar verde por vácuo. Todos os valores
 * são fictícios (`OISEG-*`). Tenant 98 (ADR 0358). MySQL-only.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS exige MySQL (ADR 0358).');
    }
    $this->oiSegEndpoint = 'connector/api/oiseg-teste-'.uniqid();
});

afterEach(function () {
    $logIds = DB::table('licenca_log')->where('endpoint', $this->oiSegEndpoint ?? '-')->pluck('id')->all();
    if ($logIds) {
        DB::table('activity_log')->where('subject_type', LicencaLog::class)->whereIn('subject_id', $logIds)->delete();
        DB::table('licenca_log')->whereIn('id', $logIds)->delete();
    }
});

it('corpo JSON: senha, contra-senha, serial e token não chegam ao body_preview; o HD continua', function () {
    $corpo = json_encode([
        ['NOME_TABELA' => 'LICENCIAMENTO', 'HD' => 'OISEG-HD-OK', 'SENHA' => 'OISEG-SEN-J1',
            'Contra_Senha' => 'OISEG-CONTRA-J2', 'serial' => 'OISEG-SERIAL-J3', 'token' => 'OISEG-TOKEN-J4'],
    ]);

    $log = oiSegPassarPeloMiddleware($this, $corpo, ['CONTENT_TYPE' => 'application/json']);
    $preview = (string) ($log->metadata['body_preview'] ?? '');

    expect($preview)->toContain('OISEG-HD-OK');
    expect($preview)->toContain('[REDACTED]');
    foreach (['OISEG-SEN-J1', 'OISEG-CONTRA-J2', 'OISEG-SERIAL-J3', 'OISEG-TOKEN-J4'] as $segredo) {
        $this->assertStringNotContainsString($segredo, $preview, "{$segredo} vazou no body_preview");
    }
});

it('corpo de formulário: os valores secretos saem, os demais campos ficam', function () {
    $corpo = 'host=OISEG-HOST-OK&senha=OISEG-SEN-F1&contra_senha=OISEG-CONTRA-F2&serial=OISEG-SERIAL-F3&token=OISEG-TOKEN-F4';

    $log = oiSegPassarPeloMiddleware($this, $corpo, ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
    $preview = (string) ($log->metadata['body_preview'] ?? '');

    expect($preview)->toContain('OISEG-HOST-OK');
    foreach (['OISEG-SEN-F1', 'OISEG-CONTRA-F2', 'OISEG-SERIAL-F3', 'OISEG-TOKEN-F4'] as $segredo) {
        $this->assertStringNotContainsString($segredo, $preview, "{$segredo} vazou no body_preview");
    }
});

it('header X-API-Key: registra que veio, nunca o valor', function () {
    $log = oiSegPassarPeloMiddleware($this, '{"hd":"OISEG-HD-H"}', [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_API_KEY' => oiSegMarcador('H1'),
    ]);

    expect($log->metadata['request_headers']['x_api_key'] ?? null)->toBe('[REDACTED]');
    $this->assertStringNotContainsString(oiSegMarcador('H1'), json_encode($log->metadata));
});

// ── Helpers (prefixo oiSeg — outros arquivos do módulo rodam no mesmo processo) ──

/** Passa uma requisição fictícia pelo `handle()` real e devolve a linha gravada em licenca_log. */
function oiSegPassarPeloMiddleware($test, string $corpo, array $server): LicencaLog
{
    $request = Request::create('/'.$test->oiSegEndpoint, 'POST', [], [], [], $server + ['REMOTE_ADDR' => '127.0.0.1'], $corpo);

    (new LogDelphiAccess())->handle($request, fn () => new Response('ok', 200));

    $log = LicencaLog::query()->where('endpoint', $test->oiSegEndpoint)->latest('id')->first();
    expect($log)->not->toBeNull();

    return $log;
}

/** Valor fictício de header, montado em runtime para não parecer chave no fonte. */
function oiSegMarcador(string $sufixo): string
{
    return implode('-', ['OISEG', 'CABECALHO', $sufixo]);
}
