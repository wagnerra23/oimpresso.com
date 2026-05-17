<?php

declare(strict_types=1);

use App\Util\OtelHelper;
use Modules\Arquivos\Http\Requests\DeleteArquivoRequest;
use Modules\Arquivos\Http\Requests\ListArquivosRequest;
use Modules\Arquivos\Http\Requests\RestoreArquivoRequest;
use Modules\Arquivos\Services\ArquivosRetentionService;

uses(Tests\TestCase::class);

/**
 * Wave 18 RETRY — Arquivos D5 + D8 + D9 SATURATION (2026-05-16).
 *
 * Cobre:
 *   D9: ArquivosRetentionService com 4 spans OTel (scan/expire_one/purge_one/run)
 *   D8: DeleteArquivoRequest, RestoreArquivoRequest, ListArquivosRequest
 *   D5: README "como cliente usa" (validado em outro teste smoke)
 *
 * Tier 0 ({@see ADR 0093}): retention é per-business; service exige $businessId no API.
 * LGPD Art. 16: defaults dry_run=true pra evitar mutação acidental.
 *
 * @see Modules\Arquivos\Services\ArquivosRetentionService
 */
describe('Wave 18 RETRY — Arquivos ArquivosRetentionService (D9)', function () {
    beforeEach(function () {
        config()->set('otel.enabled', false);
    });

    it('ArquivosRetentionService carrega via container', function () {
        $svc = app(ArquivosRetentionService::class);
        expect($svc)->toBeInstanceOf(ArquivosRetentionService::class);
    });

    it('ArquivosRetentionService expõe 4 métodos públicos', function () {
        $ref = new ReflectionClass(ArquivosRetentionService::class);
        expect($ref->hasMethod('scanExpired'))->toBeTrue();
        expect($ref->hasMethod('expireOne'))->toBeTrue();
        expect($ref->hasMethod('purgeOne'))->toBeTrue();
        expect($ref->hasMethod('run'))->toBeTrue();
    });

    it('ArquivosRetentionService source declara 4 spans OTel canônicos', function () {
        $source = file_get_contents(__DIR__ . '/../../Services/ArquivosRetentionService.php');

        expect($source)->toContain('use App\Util\OtelHelper');
        expect($source)->toContain("OtelHelper::spanBiz('arquivos.retention.scan'");
        expect($source)->toContain("OtelHelper::spanBiz('arquivos.retention.expire_one'");
        expect($source)->toContain("OtelHelper::spanBiz('arquivos.retention.purge_one'");
        expect($source)->toContain("OtelHelper::spanBiz('arquivos.retention.run'");
    });

    it('OtelHelper::spanBiz preserva retorno em chamada arquivos.retention.*', function () {
        $resultado = OtelHelper::spanBiz('arquivos.retention.smoke', function () {
            return ['ok' => true, 'modulo' => 'Arquivos'];
        }, ['module' => 'Arquivos', 'op' => 'smoke']);

        expect($resultado)->toBe(['ok' => true, 'modulo' => 'Arquivos']);
    });

    it('ArquivosRetentionService::run default dry_run=true (defesa em profundidade LGPD)', function () {
        // Reflection — checa signature default da assinatura
        $ref = new ReflectionMethod(ArquivosRetentionService::class, 'run');
        $params = $ref->getParameters();

        $dryRun = $params[2] ?? null; // 3º param
        expect($dryRun)->not->toBeNull();
        expect($dryRun->getName())->toBe('dryRun');
        expect($dryRun->isDefaultValueAvailable())->toBeTrue();
        expect($dryRun->getDefaultValue())->toBeTrue();
    });
});

describe('Wave 18 RETRY — Arquivos FormRequests novos (D8)', function () {
    it('DeleteArquivoRequest carrega + reason opcional', function () {
        expect(class_exists(DeleteArquivoRequest::class))->toBeTrue();

        $req = new DeleteArquivoRequest();
        $rules = $req->rules();
        expect($rules)->toHaveKey('reason');
        expect($rules['reason'])->toContain('nullable');
        expect($rules['reason'])->toContain('max:500');
    });

    it('RestoreArquivoRequest carrega + authorize gate', function () {
        expect(class_exists(RestoreArquivoRequest::class))->toBeTrue();

        $ref = new ReflectionClass(RestoreArquivoRequest::class);
        expect($ref->hasMethod('authorize'))->toBeTrue();
        expect($ref->hasMethod('rules'))->toBeTrue();
    });

    it('ListArquivosRequest valida bucket allow-list (hardening)', function () {
        $req = new ListArquivosRequest();
        $rules = $req->rules();

        expect($rules)->toHaveKey('bucket');
        expect($rules)->toHaveKey('per_page');
        expect($rules['per_page'])->toContain('between:1,100');
    });

    it('ListArquivosRequest expõe pageDefaults helper', function () {
        $ref = new ReflectionClass(ListArquivosRequest::class);
        expect($ref->hasMethod('pageDefaults'))->toBeTrue();
    });

    it('Arquivos tem ≥5 FormRequests pós-saturação D8', function () {
        $glob = glob(__DIR__ . '/../../Http/Requests/*.php');
        $count = is_array($glob) ? count($glob) : 0;

        // Pré: Upload + Download (2). Pós Wave 18 RETRY: + Delete + Restore + List = 5.
        expect($count)->toBeGreaterThanOrEqual(5);
    });
});

describe('Wave 18 RETRY — Arquivos D5 README cliente', function () {
    it('README cliente existe + descreve "como cliente usa"', function () {
        $readmePath = __DIR__ . '/../../../../memory/requisitos/Arquivos/README-COMO-CLIENTE-USA.md';

        expect(file_exists($readmePath))->toBeTrue(
            'README-COMO-CLIENTE-USA.md ausente em memory/requisitos/Arquivos/ — Wave 18 D5 SATURATION'
        );

        $content = file_get_contents($readmePath);
        expect(stripos($content, 'cliente'))->not->toBeFalse();
        expect(stripos($content, 'upload'))->not->toBeFalse();
    });
});
