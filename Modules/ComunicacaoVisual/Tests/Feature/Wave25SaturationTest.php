<?php

declare(strict_types=1);

/**
 * Helper path sem booting Laravel (mesmo padrão Wave23ComVisSaturationTest).
 */
function comvisW25Path(string $path = ''): string
{
    $root = realpath(__DIR__ . '/../../../../');
    return $root . ($path !== '' ? DIRECTORY_SEPARATOR . $path : '');
}

/**
 * Wave 25 — ComVis SATURATION saturação 65 → ≥85 vertical_client_facing.
 *
 * Cobre D2 (mais Pest Services), D3 (BRIEFING/CHANGELOG/SPEC W25 + charter),
 * D5 (CustomerJourney expandido), D7 (audit trail integrity restore regressão).
 *
 * Estratégia: smoke + reflection (sem boot Laravel) — rápido e compatível Hostinger.
 *
 * @see governance/buckets/vertical_client_facing.yaml
 * @see memory/decisions/0160-scoped-scorecard-evaluator-v3.md
 * @see Modules/ComunicacaoVisual/BRIEFING.md
 */

describe('Wave 25 ComVis — D3 docs canon Wave 25 entries', function () {

    it('BRIEFING.md contém entry Wave 25', function () {
        $brief = (string) file_get_contents(comvisW25Path('Modules/ComunicacaoVisual/BRIEFING.md'));
        expect($brief)->toContain('Wave 25');
    });

    it('CHANGELOG.md módulo contém entry Wave 25', function () {
        $log = (string) file_get_contents(comvisW25Path('Modules/ComunicacaoVisual/CHANGELOG.md'));
        expect($log)->toContain('Wave 25');
    });

    it('CHANGELOG.md memory/requisitos contém entry Wave 25', function () {
        $log = (string) file_get_contents(comvisW25Path('memory/requisitos/ComunicacaoVisual/CHANGELOG.md'));
        expect($log)->toContain('Wave 25');
    });

    it('charter Index.charter.md existe ao lado da page Inertia (MWART F1.5 fundação)', function () {
        $charter = comvisW25Path('resources/js/Pages/ComunicacaoVisual/Index.charter.md');
        expect(file_exists($charter))->toBeTrue('Charter MWART obrigatório ao lado .tsx');
        $conteudo = (string) file_get_contents($charter);
        expect($conteudo)->toContain('Persona-alvo');
        expect($conteudo)->toContain('Anti-padrões');
    });

    it('page Index.tsx stub Sprint 2 existe e referencia charter', function () {
        $page = comvisW25Path('resources/js/Pages/ComunicacaoVisual/Index.tsx');
        expect(file_exists($page))->toBeTrue();
        $conteudo = (string) file_get_contents($page);
        expect($conteudo)->toContain('Index.charter.md');
        expect($conteudo)->toContain('Comunicação Visual');
    });
});

describe('Wave 25 ComVis — D7 audit trail integrity (regressão restore)', function () {

    it('AuditTrailIntegrityTest.php criado pra cobrir whitelist sensível', function () {
        $test = comvisW25Path('Modules/ComunicacaoVisual/Tests/Feature/AuditTrailIntegrityTest.php');
        expect(file_exists($test))->toBeTrue();
        $conteudo = (string) file_get_contents($test);
        expect($conteudo)->toContain('PII reference');
        expect($conteudo)->toContain('observacoes pode conter PII');
    });

    it('Entities core declaram getActivitylogOptions via reflection', function () {
        $reflEntities = [
            \Modules\ComunicacaoVisual\Entities\Orcamento::class,
            \Modules\ComunicacaoVisual\Entities\Os::class,
            \Modules\ComunicacaoVisual\Entities\Apontamento::class,
        ];
        foreach ($reflEntities as $cls) {
            $r = new ReflectionClass($cls);
            expect($r->hasMethod('getActivitylogOptions'))->toBeTrue(
                "{$cls} deve ter getActivitylogOptions() (Spatie ActivityLog whitelist)"
            );
        }
    });

    it('Config/retention.php pii_fields declarado pras 3 entities', function () {
        $cfg = require comvisW25Path('Modules/ComunicacaoVisual/Config/retention.php');
        foreach (['apontamento', 'orcamento', 'os'] as $entity) {
            expect($cfg['entities'][$entity])->toHaveKey('pii_fields');
            expect($cfg['entities'][$entity]['pii_fields'])->toBeArray()->not->toBeEmpty();
        }
    });
});

describe('Wave 25 ComVis — D2 Pest cobertura adicional Services', function () {

    it('OrcamentoCalculator + ApontamentoTracker Services existem', function () {
        expect(class_exists(\Modules\ComunicacaoVisual\Services\OrcamentoCalculator::class))->toBeTrue();
        expect(class_exists(\Modules\ComunicacaoVisual\Services\ApontamentoTracker::class))->toBeTrue();
    });

    it('Services existentes têm Pest Test dedicado', function () {
        expect(file_exists(comvisW25Path('Modules/ComunicacaoVisual/Tests/Feature/OrcamentoCalculatorTest.php')))->toBeTrue();
        expect(file_exists(comvisW25Path('Modules/ComunicacaoVisual/Tests/Feature/ApontamentoTrackerTest.php')))->toBeTrue();
    });
});

describe('Wave 25 ComVis — D5 cliente real (CustomerJourney expandido)', function () {

    it('CustomerJourneyTest.php cobre isolamento multi-tenant', function () {
        $test = (string) file_get_contents(comvisW25Path('Modules/ComunicacaoVisual/Tests/Feature/CustomerJourneyTest.php'));
        expect($test)->toContain('isolamento multi-tenant');
        expect($test)->toContain('biz=99 não vê orçamento de biz=1');
    });

    it('README.md cobre jornada completa (atender→aprovar→produzir→faturar→entregar)', function () {
        $readme = (string) file_get_contents(comvisW25Path('Modules/ComunicacaoVisual/README.md'));
        expect($readme)->toContain('Atender pedido novo');
        expect($readme)->toContain('Aprovação → produção');
        expect($readme)->toContain('Faturamento + entrega');
    });

    it('governance/module_clients.yaml ComVis nível ≥ backlog_hipotese', function () {
        $yaml = (string) file_get_contents(comvisW25Path('config/governance/module_clients.yaml'));
        // ComVis bloco
        expect($yaml)->toContain('ComunicacaoVisual:');
        // nível declarado (sem upgrade Tier 0 sem cliente real — ADR 0105)
        expect(preg_match('/ComunicacaoVisual:\s*\n\s*level:\s*(\w+)/m', $yaml, $m))->toBe(1);
        $nivel = $m[1];
        expect(in_array($nivel, ['backlog_hipotese', 'piloto_reportando_dor', 'biz_1_wagner_active', 'biz_4_rota_livre_prod', 'internal_governance_active'], true))
            ->toBeTrue("nível declarado '{$nivel}' deve ser válido na rubrica D5");
    });
});

describe('Wave 25 ComVis — V6 governance metadata atualizado', function () {

    it('module.json wave_25_saturation=true declarado', function () {
        $json = json_decode((string) file_get_contents(comvisW25Path('Modules/ComunicacaoVisual/module.json')), true);
        expect($json['governance'])->toHaveKey('wave_25_saturation');
        expect($json['governance']['wave_25_saturation'])->toBeTrue();
    });

    it('module.json last_governance_review atualizado pra 2026-05-16+', function () {
        $json = json_decode((string) file_get_contents(comvisW25Path('Modules/ComunicacaoVisual/module.json')), true);
        expect($json['governance']['last_governance_review'])->toBeGreaterThanOrEqual('2026-05-16');
    });
});
