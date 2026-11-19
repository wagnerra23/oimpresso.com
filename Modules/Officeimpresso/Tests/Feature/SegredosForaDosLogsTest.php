<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Officeimpresso\Entities\Licenca_Computador;
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
 * M2/M3 (ActivityLog das entidades): `Licenca_Computador` e `LicencaLog` deixaram a lista grandfathered
 * do MultiTenantScopeArchitectureTest pelo contrato global (decisão [W] 2026-11-19, ADR TECH-0001 do
 * módulo). Os dois últimos casos provam que credencial, corpo, IP e user agent não vão para activity_log.
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
    if (! empty($this->oiSegMaquinaId)) {
        DB::table('activity_log')->where('subject_type', Licenca_Computador::class)->where('subject_id', $this->oiSegMaquinaId)->delete();
        DB::table('licenca_computador')->where('id', $this->oiSegMaquinaId)->delete();
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

it('M3 — a linha de licenca_log entra no activity_log sem corpo, trecho do token, IP e user agent', function () {
    $log = oiSegPassarPeloMiddleware($this, '{"hd":"OISEG-HD-M3","senha":"OISEG-SEN-M3"}', [
        'CONTENT_TYPE'    => 'application/json',
        'HTTP_USER_AGENT' => 'OISEG-AGENTE-M3',
    ]);

    $props = DB::table('activity_log')
        ->where('subject_type', LicencaLog::class)->where('subject_id', $log->id)
        ->value('properties');

    // Âncora positiva: a linha existe e carrega um campo comum.
    expect($props)->not->toBeNull();
    expect((string) $props)->toContain($this->oiSegEndpoint);
    foreach (['body_preview', 'OISEG-HD-M3', 'OISEG-SEN-M3', 'OISEG-AGENTE-M3', '"ip"', '"token_hint"', '"metadata"'] as $fora) {
        $this->assertStringNotContainsString($fora, (string) $props, "{$fora} chegou ao activity_log");
    }
});

it('M2 — salvar a licença registra a mudança no activity_log sem senha, contra-senha, token, serial, usuário e conexão', function () {
    $this->oiSegMaquinaId = (int) DB::table('licenca_computador')->insertGetId([
        'business_id' => (int) $this->seededTenant()->id,
        'hd'          => 'OISEG-HD-M2-'.uniqid(),
        'hostname'    => 'OISEG-HOST-ANTES',
        'bloqueado'   => 0,
    ]);

    $maquina = Licenca_Computador::query()->findOrFail($this->oiSegMaquinaId);
    $maquina->hostname = 'OISEG-HOST-DEPOIS';
    $segredos = [
        'senha'        => 'OISEG-SEN-M2',
        'contra_senha' => 'OISEG-CONTRA-M2',
        'token'        => 'OISEG-TOKEN-M2',
        'serial'       => 'OISEG-SERIAL-M2',
        'usuario'      => 'OISEG-USUARIO-M2',
        'conexao'      => 'OISEG-CONEXAO-M2',
    ];
    foreach ($segredos as $campo => $valor) {
        $maquina->{$campo} = $valor;
    }
    $maquina->save();

    $props = (string) DB::table('activity_log')
        ->where('subject_type', Licenca_Computador::class)->where('subject_id', $this->oiSegMaquinaId)
        ->latest('id')->value('properties');

    // Âncora positiva: a mudança comum foi registrada.
    expect($props)->toContain('OISEG-HOST-DEPOIS');
    foreach ($segredos as $campo => $valor) {
        $this->assertStringNotContainsString($valor, $props, "{$campo} chegou ao activity_log");
    }
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
