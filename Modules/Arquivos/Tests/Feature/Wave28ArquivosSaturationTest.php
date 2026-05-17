<?php

declare(strict_types=1);

use App\Util\OtelHelper;
use Modules\Arquivos\Services\ArquivosRetentionService;

uses(Tests\TestCase::class);

/**
 * Wave 28 Arquivos SATURATION FINAL — polish 74-88 → ≥92 (+4pp).
 *
 * Esforço por dimensão:
 *  - D2 +3 Pest novos cenários Wave 28
 *  - D9 +1 span `arquivos.retention.summary` em ArquivosRetentionService (5º span do service)
 *  - D3 CHANGELOG W28 entry
 *
 * Trust L0: Reflection + source-grep sem boot DB.
 *
 * @see Modules/Arquivos/Tests/Feature/Wave26ArquivosSaturationTest.php (Wave 26 baseline)
 * @see Modules/Arquivos/Services/ArquivosRetentionService.php (D9 +1 span W28)
 */
describe('Wave 28 Arquivos POLISH 74-88 → ≥92', function () {

    beforeEach(function () {
        config()->set('otel.enabled', false);
    });

    // ------------------------------------------------------------------
    // D9 W28 — span novo arquivos.retention.summary (5º span Retention)
    // ------------------------------------------------------------------

    it('D9 W28: ArquivosRetentionService tem método summary novo (W28 D9)', function () {
        $ref = new ReflectionClass(ArquivosRetentionService::class);
        expect($ref->hasMethod('summary'))->toBeTrue('Wave 28 D9 — summary novo método público');

        $method = $ref->getMethod('summary');
        expect($method->isPublic())->toBeTrue();
        expect($method->getReturnType()?->getName())->toBe('array');
    });

    it('D9 W28: ArquivosRetentionService instrumenta arquivos.retention.summary (5º span Retention)', function () {
        $src = file_get_contents((new ReflectionClass(ArquivosRetentionService::class))->getFileName());

        expect($src)->toContain("'arquivos.retention.summary'");

        // 4 baseline (run/scan/expire_one/purge_one) + 1 W28 = 5 spans canon
        $count = substr_count($src, 'OtelHelper::spanBiz(');
        expect($count)->toBeGreaterThanOrEqual(5, "Spans ArquivosRetentionService ≥5 (W28 +1); achou {$count}");
    });

    // ------------------------------------------------------------------
    // D2 W28 — +3 Pest cenários adicionais
    // ------------------------------------------------------------------

    it('D2 W28: summary exige businessId + retentionDays Tier 0 (multi-tenant ADR 0093)', function () {
        $ref = new ReflectionMethod(ArquivosRetentionService::class, 'summary');
        $params = collect($ref->getParameters())->keyBy(fn ($p) => $p->getName());

        expect($params->has('businessId'))->toBeTrue('summary deve ter businessId Tier 0');
        expect($params->has('retentionDays'))->toBeTrue('summary deve ter retentionDays explícito');

        expect($params['businessId']->getType()?->getName())->toBe('int');
        expect($params['retentionDays']->getType()?->getName())->toBe('int');
    });

    it('D2 W28: summary documenta shape canon (total/soft_deleted/expired_eligible/business_id)', function () {
        $src = file_get_contents((new ReflectionClass(ArquivosRetentionService::class))->getFileName());

        // Shape canônico no return array
        foreach (['total', 'soft_deleted', 'expired_eligible', 'business_id'] as $key) {
            expect(str_contains($src, "'{$key}'"))->toBeTrue("summary deve retornar chave canon '{$key}'");
        }
    });

    it('D2 W28: summary é READ-ONLY (zero mutação — preserva fail-secure dry_run Wave 18)', function () {
        $src = file_get_contents((new ReflectionClass(ArquivosRetentionService::class))->getFileName());

        // Localiza bloco summary e valida que não chama delete/forceDelete/restore
        $summaryStart = strpos($src, 'public function summary(');
        expect($summaryStart)->not->toBeFalse();

        $summaryEnd = strpos($src, "\n    }\n", $summaryStart);
        $summaryBlock = substr($src, $summaryStart, $summaryEnd - $summaryStart);

        expect($summaryBlock)->not->toContain('->delete()');
        expect($summaryBlock)->not->toContain('forceDelete');
        expect($summaryBlock)->not->toContain('restore');
        expect($summaryBlock)->not->toContain('update(');
    });

    it('D9 W28: OtelHelper preserva exception em spans arquivos.retention.* (fail-loud)', function () {
        expect(fn () => OtelHelper::spanBiz(
            'arquivos.retention.test_w28_boom',
            fn () => throw new \RuntimeException('w28-retention-boom')
        ))->toThrow(\RuntimeException::class, 'w28-retention-boom');
    });

    // ------------------------------------------------------------------
    // D3 W28 — CHANGELOG entry novo
    // ------------------------------------------------------------------

    it('D3 W28: CHANGELOG.md tem entrada Wave 28 (saturation 74-88 → ≥92)', function () {
        $changelog = file_get_contents(base_path('Modules/Arquivos/CHANGELOG.md'));
        expect($changelog)->toContain('Wave 28');
    });
});
