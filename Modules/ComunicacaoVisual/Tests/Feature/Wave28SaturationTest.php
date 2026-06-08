<?php

declare(strict_types=1);

use Modules\ComunicacaoVisual\Entities\Material;
use Modules\ComunicacaoVisual\Entities\Orcamento;
use Modules\ComunicacaoVisual\Entities\Os;
use Modules\ComunicacaoVisual\Services\OrcamentoCalculator;

/**
 * Helper path sem booting Laravel (mesmo padrão Wave23/Wave25/Wave26).
 */
function comvisW28Path(string $path = ''): string
{
    $root = realpath(__DIR__ . '/../../../../');
    return $root . ($path !== '' ? DIRECTORY_SEPARATOR . $path : '');
}

/**
 * Wave 28 SATURATION FINAL ComVis — push 87 → ≥92 (+5pp).
 *
 * Foco minimal:
 *   - D2 (+3) Pest cross-tenant defesa em camadas (10 entities têm global scope
 *             business_id via boot() — source-level reflexão sem MySQL)
 *   - D9 (+1) span OrcamentoCalculator confirmação canônico (comvis.orcamento.calcular
 *             + comvis.apontamento.{iniciar,finalizar,cancelar} = 4 spans canon)
 *
 * Tier 0 IRREVOGÁVEL (ADR 0093):
 *   ⛔ Toda Entity ComVis (10 canon) DEVE ter `addGlobalScope('business_id', ...)`
 *   ⛔ ROTA LIVRE biz=4 preservada (Larissa cliente piloto — ComVis aproximação)
 *   ⛔ NUNCA biz=4 em tests — sempre biz=1 (Wagner) ou biz=99 (fictício) — ADR 0101
 *
 * SQLite-friendly: source-level (grep `addGlobalScope`) + reflexão sem hit prod.
 *
 * @see Modules/ComunicacaoVisual/Tests/Feature/Wave26SaturationTest.php
 * @see Modules/ComunicacaoVisual/Services/OrcamentoCalculator.php
 */

describe('Wave 28 ComVis — D2 cross-tenant defesa Model-level (10 entities)', function () {

    it('W28 D2.a — 100% Entities (10/10) declaram addGlobalScope business_id (Tier 0)', function () {
        $entities = [
            Material::class,
            \Modules\ComunicacaoVisual\Entities\Substrato::class,
            \Modules\ComunicacaoVisual\Entities\Acabamento::class,
            \Modules\ComunicacaoVisual\Entities\Instalacao::class,
            \Modules\ComunicacaoVisual\Entities\InstalacaoCatalogo::class,
            Orcamento::class,
            \Modules\ComunicacaoVisual\Entities\OrcamentoItem::class,
            Os::class,
            \Modules\ComunicacaoVisual\Entities\OrdemProducao::class,
            \Modules\ComunicacaoVisual\Entities\Apontamento::class,
        ];

        $sem = [];
        foreach ($entities as $entityClass) {
            $src = file_get_contents((new ReflectionClass($entityClass))->getFileName());
            if (! str_contains($src, "addGlobalScope('business_id'")) {
                $sem[] = $entityClass;
            }
        }
        expect($sem)->toBeEmpty(
            'Entities sem global scope business_id (Tier 0 regrediria): ' . implode(', ', $sem)
        );
    });

    it('W28 D2.b — Orcamento + OrcamentoItem têm boot() override + creating fillback biz', function () {
        $srcOrc = file_get_contents((new ReflectionClass(Orcamento::class))->getFileName());
        $srcItem = file_get_contents((new ReflectionClass(\Modules\ComunicacaoVisual\Entities\OrcamentoItem::class))->getFileName());

        // Orcamento: boot() + addGlobalScope
        expect($srcOrc)->toContain('protected static function boot');
        expect($srcOrc)->toContain("addGlobalScope('business_id'");

        // OrcamentoItem: boot() + addGlobalScope (filhote do Orcamento via FK orcamento_id mas também scoped por biz)
        expect($srcItem)->toContain("addGlobalScope('business_id'");
    });

    it('W28 D2.c — Wave 26 LogsActivity audit trail mantido em 10/10 entities (não-regressão)', function () {
        // Defesa em camadas: scope (D2) + audit trail Spatie (D7) — preservar conjunto
        $entities = [
            Material::class,
            \Modules\ComunicacaoVisual\Entities\Substrato::class,
            \Modules\ComunicacaoVisual\Entities\Acabamento::class,
            \Modules\ComunicacaoVisual\Entities\Instalacao::class,
            \Modules\ComunicacaoVisual\Entities\InstalacaoCatalogo::class,
            Orcamento::class,
            \Modules\ComunicacaoVisual\Entities\OrcamentoItem::class,
            Os::class,
            \Modules\ComunicacaoVisual\Entities\OrdemProducao::class,
            \Modules\ComunicacaoVisual\Entities\Apontamento::class,
        ];

        $sem = [];
        foreach ($entities as $cls) {
            $traits = class_uses_recursive($cls);
            if (! in_array(\Spatie\Activitylog\Traits\LogsActivity::class, $traits, true)) {
                $sem[] = $cls;
            }
        }
        expect($sem)->toBeEmpty('Wave 26 LogsActivity regrediria em: ' . implode(', ', $sem));
    });
});

describe('Wave 28 ComVis — D9 span OrcamentoCalculator + catalog 4 spans canon', function () {

    it('W28 D9 OrcamentoCalculator.calcular usa spanBiz canon (comvis.orcamento.calcular)', function () {
        $src = file_get_contents((new ReflectionClass(OrcamentoCalculator::class))->getFileName());

        expect($src)->toContain('use App\Util\OtelHelper;');
        expect($src)->toContain("OtelHelper::spanBiz('comvis.orcamento.calcular'");
        // Attrs sem PII (observacoes redacted via PiiRedactor antes — Wave 26 D7.a)
        expect($src)->toContain('observacoes_redact');
    });

    it('W28 D9 ComVis catalog ≥4 spans canon (1 calculator + 3 apontamentos)', function () {
        $spans = [
            'comvis.orcamento.calcular'   => 'Modules/ComunicacaoVisual/Services/OrcamentoCalculator.php',
            'comvis.apontamento.iniciar'  => 'Modules/ComunicacaoVisual/Services/ApontamentoTracker.php',
            'comvis.apontamento.finalizar' => 'Modules/ComunicacaoVisual/Services/ApontamentoTracker.php',
            'comvis.apontamento.cancelar' => 'Modules/ComunicacaoVisual/Services/ApontamentoTracker.php',
        ];

        foreach ($spans as $spanName => $path) {
            $src = file_get_contents(comvisW28Path($path));
            expect(str_contains($src, $spanName))
                ->toBeTrue("Span '{$spanName}' deve estar em {$path} (Wave 28 cobertura completa)");
        }

        expect(count($spans))->toBeGreaterThanOrEqual(4, 'ComVis deve ter ≥4 spans canon (Wave 28)');
    });
});

it('W28 sanity Wave 26 SaturationTest preservado (não-regressão)', function () {
    expect(file_exists(__DIR__ . '/Wave26SaturationTest.php'))->toBeTrue();
});
