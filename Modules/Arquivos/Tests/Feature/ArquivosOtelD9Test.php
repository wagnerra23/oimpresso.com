<?php

declare(strict_types=1);

use App\Util\OtelHelper;
use Modules\Arquivos\Entities\Arquivo;
use Modules\Arquivos\Services\ArquivosService;
use Modules\Arquivos\Services\Curador\CuradorEngine;

uses(Tests\TestCase::class);

/**
 * Wave 16 governance — D9 OTel observability Modules/Arquivos.
 *
 * Cenarios cobertos:
 *  1. ArquivosService usa OtelHelper (importacao + presenca do uso)
 *  2. OtelHelper zero-cost preserva semantica do callback
 *  3. Reflection — metodos attach/classify/signedUrl/softDelete/restore wrap em OtelHelper
 *
 * Tier 0: nao toca biz prod; usa OtelHelper direto sem session.
 *
 * @see memory/decisions/0155-module-grade-v3.md D9.a
 * @see app/Util/OtelHelper.php
 * @see memory/decisions/0123-modules-arquivos-backbone.md
 */

beforeEach(function () {
    config()->set('otel.enabled', false);
});

it('D9.a — OtelHelper::spanBiz envolve callback Arquivos sem alterar retorno', function () {
    $resultado = OtelHelper::spanBiz('arquivos.test_smoke', function () {
        return ['ok' => true, 'modulo' => 'Arquivos'];
    }, ['module' => 'Arquivos', 'op' => 'test_smoke']);

    expect($resultado)->toBe(['ok' => true, 'modulo' => 'Arquivos']);
});

it('D9.a — ArquivosService source contem chamadas OtelHelper::spanBiz nos metodos canon', function () {
    $source = file_get_contents(__DIR__ . '/../../Services/ArquivosService.php');

    expect($source)->toContain('use App\Util\OtelHelper');
    expect($source)->toContain("OtelHelper::spanBiz('arquivos.attach'");
    expect($source)->toContain("OtelHelper::spanBiz('arquivos.classify'");
    expect($source)->toContain("OtelHelper::spanBiz('arquivos.signed_url'");
    expect($source)->toContain("OtelHelper::spanBiz('arquivos.soft_delete'");
    expect($source)->toContain("OtelHelper::spanBiz('arquivos.restore'");
});

it('D9.b — ArquivosService.attach() emite log estruturado arquivos.upload', function () {
    $source = file_get_contents(__DIR__ . '/../../Services/ArquivosService.php');
    // Verifica Log::info('arquivos.upload', ...) presente.
    expect($source)->toContain("Log::info('arquivos.upload'");
});

it('D9.c — arquivos:health-check existente (Sprint 2 ADR 0123) — sanity check', function () {
    $output = \Illuminate\Support\Facades\Artisan::all();
    expect($output)->toHaveKey('arquivos:health-check');
});

it('D9.a — CuradorEngine instrumenta o span canon (era o ultimo Service sem OTel)', function () {
    $source = file_get_contents(__DIR__ . '/../../Services/Curador/CuradorEngine.php');

    expect($source)->toContain('use App\Util\OtelHelper');
    expect($source)->toContain("OtelHelper::spanBiz('arquivos.curador.classify'");
});

it('D9.a — span do Curador e TRANSPARENTE: classify() devolve o mesmo com OTel on/off', function () {
    // Comportamento, nao presenca. Nao toca a tabela arquivos: o model so e construido,
    // nunca persistido — mesmo caminho que o ScaffoldTest ja exercita sem guarda. Entao
    // este caso roda em QUALQUER lane, e isso importa porque o CuradorEngineTest, que e o
    // guarda comportamental do engine, faz markTestSkipped quando a tabela falta.
    $engine = new CuradorEngine();
    $stub   = new Arquivo([
        'original_name' => '.env',
        'storage_path'  => 'biz-98/2026/08/abc123.env',
        'size_bytes'    => 9280,
        'mime_type'     => 'text/plain',
        'md5'           => str_repeat('a', 32),
    ]);

    config()->set('otel.enabled', false);
    $desligado = $engine->classify($stub);

    // Com OTel ligado o SDK esta ausente na suite, entao o helper cai no pass-through —
    // o valor tem que ser o MESMO: o trace mede, nunca altera.
    config()->set('otel.enabled', true);
    $ligado = $engine->classify($stub);

    expect($desligado['bucket'])->toBe('sensitive');
    expect($desligado['rule_matched'])->toBe('sensitive_env_real');
    expect($ligado)->toBe($desligado);
});
