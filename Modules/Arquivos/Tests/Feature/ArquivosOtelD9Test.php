<?php

declare(strict_types=1);

use App\Util\OtelHelper;
use Modules\Arquivos\Services\ArquivosService;
use Modules\Arquivos\Services\CofreStatsReader;

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

it('D9.a — Curador e Cofre instrumentam os spans canon (eram os 2 Services sem OTel)', function () {
    $curador = file_get_contents(__DIR__ . '/../../Services/Curador/CuradorEngine.php');
    $cofre   = file_get_contents(__DIR__ . '/../../Services/CofreStatsReader.php');

    expect($curador)->toContain('use App\Util\OtelHelper');
    expect($curador)->toContain("OtelHelper::spanBiz('arquivos.curador.classify'");

    expect($cofre)->toContain('use App\Util\OtelHelper');
    expect($cofre)->toContain("OtelHelper::spanBiz('arquivos.cofre.stats'");
});

it('D9.a — span do Cofre e TRANSPARENTE: portao fail-closed segue devolvendo retrato vazio', function () {
    // Comportamento, nao presenca. Sem tenant na sessao o fetch() curto-circuita ANTES de
    // tocar Schema/banco, entao este caso roda em qualquer lane (inclusive sem a tabela
    // arquivos) e prova que envelopar em spanBiz nao alterou o retorno nem o portao Tier 0.
    session()->forget(['user.business_id', 'business.id']);

    $retrato = (new CofreStatsReader())->fetch();

    expect($retrato['disponivel'])->toBeFalse();
    expect($retrato['discos'])->toBe([]);
    expect($retrato['duplicados'])->toBe(['grupos' => 0, 'registros' => 0, 'truncado' => false, 'exemplos' => []]);
});

it('D9.a — span do Cofre e transparente TAMBEM com otel.enabled=true (SDK ausente = pass-through)', function () {
    // O caminho zero-cost (enabled=false) e o caminho com OTel ligado tem que devolver o
    // MESMO valor — senao o trace estaria mudando o comportamento que ele so deveria medir.
    session()->forget(['user.business_id', 'business.id']);

    config()->set('otel.enabled', false);
    $desligado = (new CofreStatsReader())->fetch();

    config()->set('otel.enabled', true);
    $ligado = (new CofreStatsReader())->fetch();

    expect($ligado)->toBe($desligado);
});
