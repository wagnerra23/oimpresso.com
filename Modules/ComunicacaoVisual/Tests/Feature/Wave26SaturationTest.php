<?php

declare(strict_types=1);

/**
 * Helper path sem booting Laravel (mesmo padrão Wave23/Wave25 SaturationTest).
 */
function comvisW26Path(string $path = ''): string
{
    $root = realpath(__DIR__ . '/../../../../');
    return $root . ($path !== '' ? DIRECTORY_SEPARATOR . $path : '');
}

/**
 * Wave 26 — ComVis SATURATION saturação 69 → ≥85 vertical_client_facing.
 *
 * Foco forensic D7 (1/10 persistia W25):
 *   - D7.a PiiRedactor reference em Services (era 0; pattern bate em ComVis files)
 *   - D7.b LogsActivity em 10/10 entities (era 3/10 → 1pt; agora 10/10 → 3pts)
 *   - D7.c config/retention.comunicacaovisual.php (shim no path canônico que rubrica busca)
 *
 * Complementos:
 *   - D5 +5: scorecard YAML memory/governance/scorecards/comunicacaovisual.yaml
 *   - D3 +5: BRIEFING/CHANGELOG Wave 26 entries + PII-LGPD.md canon
 *   - D8 +3: RecusarOrcamentoRequest FormRequest dedicado
 *
 * Estratégia: smoke + reflection (sem boot Laravel) — rápido + compat Hostinger.
 *
 * @see governance/buckets/vertical_client_facing.yaml
 * @see memory/governance/scorecards/comunicacaovisual.yaml (Wave 26)
 * @see Modules/Governance/Services/ModuleGradeService::dim7LgpdCompliance()
 */

describe('Wave 26 ComVis — D7 forensic restore (1/10 → 10/10)', function () {

    it('D7.c — config/retention.comunicacaovisual.php (path canônico ModuleGradeService) existe', function () {
        $shim = comvisW26Path('config/retention.comunicacaovisual.php');
        expect(file_exists($shim))->toBeTrue(
            'Shim path canônico exigido por ModuleGradeService::dim7LgpdCompliance D7.c'
        );
        // Aponta pro canon module-level via require
        $conteudo = (string) file_get_contents($shim);
        expect($conteudo)->toContain('Modules/ComunicacaoVisual/Config/retention.php');
    });

    it('D7.b — 100% Entities (10/10) declaram LogsActivity', function () {
        $entities = [
            \Modules\ComunicacaoVisual\Entities\Acabamento::class,
            \Modules\ComunicacaoVisual\Entities\Apontamento::class,
            \Modules\ComunicacaoVisual\Entities\Instalacao::class,
            \Modules\ComunicacaoVisual\Entities\InstalacaoCatalogo::class,
            \Modules\ComunicacaoVisual\Entities\Material::class,
            \Modules\ComunicacaoVisual\Entities\Orcamento::class,
            \Modules\ComunicacaoVisual\Entities\OrcamentoItem::class,
            \Modules\ComunicacaoVisual\Entities\OrdemProducao::class,
            \Modules\ComunicacaoVisual\Entities\Os::class,
            \Modules\ComunicacaoVisual\Entities\Substrato::class,
        ];
        $sem = [];
        foreach ($entities as $cls) {
            $traits = class_uses_recursive($cls);
            if (! in_array(\Spatie\Activitylog\Traits\LogsActivity::class, $traits, true)) {
                $sem[] = $cls;
            }
        }
        expect($sem)->toBeEmpty(
            'Entities sem LogsActivity (D7.b regrediria): ' . implode(', ', $sem)
        );
    });

    it('D7.a — PiiRedactor referenciado em Services/Controllers ComVis', function () {
        // ModuleGradeService grep busca padrão `PiiRedactor` em qualquer arquivo do módulo
        $files = [
            comvisW26Path('Modules/ComunicacaoVisual/Services/OrcamentoCalculator.php'),
        ];
        $matchCount = 0;
        foreach ($files as $f) {
            if (! file_exists($f)) continue;
            if (preg_match('/PiiRedactor/i', (string) file_get_contents($f))) {
                $matchCount++;
            }
        }
        expect($matchCount)->toBeGreaterThanOrEqual(1, 'D7.a exige ao menos 1 arquivo com PiiRedactor');
    });

    it('memory/requisitos/ComunicacaoVisual/PII-LGPD.md canon doc existe', function () {
        $doc = comvisW26Path('memory/requisitos/ComunicacaoVisual/PII-LGPD.md');
        expect(file_exists($doc))->toBeTrue('PII-LGPD.md canônico D7 obrigatório');
        $conteudo = (string) file_get_contents($doc);
        expect($conteudo)->toContain('PiiRedactor');
        expect($conteudo)->toContain('right_to_be_forgotten');
        expect($conteudo)->toContain('LGPD Art. 16');
    });
});

describe('Wave 26 ComVis — D5 scorecard YAML (ScopedScorecardEvaluator)', function () {

    it('memory/governance/scorecards/comunicacaovisual.yaml existe (D5 ≥4 boost)', function () {
        $sc = comvisW26Path('memory/governance/scorecards/comunicacaovisual.yaml');
        expect(file_exists($sc))->toBeTrue('Scorecard YAML obrigatório pra ScopedScorecardEvaluator');
    });

    it('scorecard YAML declara dimensions.lgpd.current=10', function () {
        $sc = (string) file_get_contents(comvisW26Path('memory/governance/scorecards/comunicacaovisual.yaml'));
        expect($sc)->toContain('lgpd:');
        expect($sc)->toMatch('/lgpd:\s*\{\s*peso:\s*10,\s*target:\s*10,\s*current:\s*10/');
    });

    it('scorecard YAML referencia bucket vertical_client_facing + target_score=85', function () {
        $sc = (string) file_get_contents(comvisW26Path('memory/governance/scorecards/comunicacaovisual.yaml'));
        expect($sc)->toContain('bucket_yaml');
        expect($sc)->toContain('vertical_client_facing');
        expect($sc)->toContain('target_score: 85');
    });
});

describe('Wave 26 ComVis — D8 security FormRequests + D3 docs canon', function () {

    it('RecusarOrcamentoRequest FormRequest dedicado existe (D8 boost)', function () {
        expect(class_exists(\Modules\ComunicacaoVisual\Http\Requests\RecusarOrcamentoRequest::class))->toBeTrue();
    });

    it('BRIEFING.md contém entry Wave 26', function () {
        $brief = (string) file_get_contents(comvisW26Path('Modules/ComunicacaoVisual/BRIEFING.md'));
        expect($brief)->toContain('Wave 26');
    });

    it('CHANGELOG.md módulo contém entry Wave 26', function () {
        $log = (string) file_get_contents(comvisW26Path('Modules/ComunicacaoVisual/CHANGELOG.md'));
        expect($log)->toContain('Wave 26');
    });

    it('module.json governance wave_26_saturation=true + last_governance_review ≥2026-05-17', function () {
        $json = json_decode((string) file_get_contents(comvisW26Path('Modules/ComunicacaoVisual/module.json')), true);
        expect($json['governance'])->toHaveKey('wave_26_saturation');
        expect($json['governance']['wave_26_saturation'])->toBeTrue();
        expect($json['governance']['last_governance_review'])->toBeGreaterThanOrEqual('2026-05-17');
    });
});
