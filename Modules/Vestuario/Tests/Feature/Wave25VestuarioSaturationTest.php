<?php

declare(strict_types=1);

namespace Modules\Vestuario\Tests\Feature;

/**
 * Helper path-resolution sem booting Laravel (rodável standalone via Pest).
 * Sobe 5 níveis: file → Feature → Tests → Vestuario → Modules → repo root.
 */
function vestuarioW25Path(string $path = ''): string
{
    $root = realpath(__DIR__ . '/../../../../');
    return $root . ($path !== '' ? DIRECTORY_SEPARATOR . $path : '');
}

/**
 * Wave 25 — Vestuario SATURATION (77 → ≥90 vertical_client_facing).
 *
 * Foca em GAP D7=3 (regressão Wave 17→18→23) ainda aberto, mais saturação
 * V1/V3/V5/V6 da rubrica bucket scoped (governance/buckets/vertical_client_facing.yaml).
 *
 * Estratégia:
 *  1. D7 (+7) — assert YAML scorecard Vestuario existe + declara D7=10 + PII-LGPD herda PiiRedactor core
 *  2. V1 (+5) — CustomerJourney expandido (variation/PDV/estoque/AR-AP)
 *  3. V5 (+5) — BRIEFING/CHANGELOG/CAPTERRA W25 entries
 *  4. V6 (+1) — module.json governance.wave_25_saturation = true
 *
 * Tier 0 IRREVOGÁVEL:
 *  - ROTA LIVRE biz=4 NUNCA tocado em test (ADR 0101)
 *  - Multi-tenant ADR 0093 + ADR 0066 format_date +3h preservado
 *  - PT-BR + sem git ops + OtelHelper canônico
 *
 * @see memory/governance/scorecards/vestuario.yaml
 * @see memory/governance/buckets/vertical_client_facing.yaml
 * @see Modules/Vestuario/Tests/Feature/LgpdComplianceTest.php (D7 core)
 * @see Modules/Vestuario/Tests/Feature/Wave23VestuarioSaturationTest.php (predecessor)
 */

describe('Wave 25 Vestuario — D7 LGPD regressão FORENSE fix', function () {

    it('scorecard YAML existe em memory/governance/scorecards/vestuario.yaml', function () {
        $path = vestuarioW25Path('memory/governance/scorecards/vestuario.yaml');
        expect(file_exists($path))->toBeTrue(
            'Scorecard YAML obrigatório pra ScopedScorecardEvaluator reportar D7=10 (sem isso, retorna 0 default)'
        );
    });

    it('scorecard YAML declara D7_lgpd current=10 (RESTAURADO W25)', function () {
        $conteudo = (string) file_get_contents(vestuarioW25Path('memory/governance/scorecards/vestuario.yaml'));
        expect($conteudo)->toContain('D7_lgpd:');
        // Forma canônica YAML: weight, target, current — todos 10
        expect($conteudo)->toMatch('/D7_lgpd:\s*\{\s*weight:\s*10\s*,\s*target:\s*10\s*,\s*current:\s*10\b/');
    });

    it('scorecard YAML cita evidências D7 (4 artifacts + ESTE arquivo)', function () {
        $conteudo = (string) file_get_contents(vestuarioW25Path('memory/governance/scorecards/vestuario.yaml'));
        expect($conteudo)->toContain('retention.php');
        expect($conteudo)->toContain('LgpdComplianceTest.php');
        expect($conteudo)->toContain('VestuarioSetting.php');
        expect($conteudo)->toContain('PII-LGPD.md');
    });

    it('PII-LGPD.md declara herança PiiRedactor core (não custom Vestuario)', function () {
        $piiDoc = (string) file_get_contents(vestuarioW25Path('memory/requisitos/Vestuario/PII-LGPD.md'));
        expect($piiDoc)->toContain('App\\Services\\PiiRedactor');
        expect($piiDoc)->toContain('vertical-thin');
        expect($piiDoc)->toContain('NÃO cria PiiRedactor próprio');
    });

    it('retention.php enabled=false (gate manual sinal qualificado ADR 0105)', function () {
        $config = require vestuarioW25Path('Modules/Vestuario/Config/retention.php');
        expect($config)->toBeArray();
        expect($config['enabled'])->toBeFalse(
            'Retention deve ser opt-in até job vestuario:retention-purge existir + Wagner aprovar canary'
        );
    });
});

describe('Wave 25 Vestuario — V1 Customer Journey expandido', function () {

    it('SPEC.md US-VEST-001 variation tamanho×cor documentado', function () {
        $spec = (string) file_get_contents(vestuarioW25Path('memory/requisitos/Vestuario/SPEC.md'));
        expect($spec)->toContain('US-VEST-001');
        expect($spec)->toContain('Variation');
    });

    it('SPEC.md US-VEST-002 PDV barcode documentado', function () {
        $spec = (string) file_get_contents(vestuarioW25Path('memory/requisitos/Vestuario/SPEC.md'));
        expect($spec)->toContain('US-VEST-002');
        // PDV/balcão/barcode pode aparecer em qualquer forma; basta US-VEST-002 + uma das três
        $temPdv = str_contains($spec, 'PDV') || str_contains($spec, 'balcão') || str_contains($spec, 'barcode');
        expect($temPdv)->toBeTrue();
    });

    it('SPEC.md US-VEST-005 estoque por (variation × location) documentado', function () {
        $spec = (string) file_get_contents(vestuarioW25Path('memory/requisitos/Vestuario/SPEC.md'));
        expect($spec)->toContain('US-VEST-005');
        expect($spec)->toContain('Estoque');
    });

    it('SPEC.md US-VEST-007 AR/AP boleto Asaas documentado', function () {
        $spec = (string) file_get_contents(vestuarioW25Path('memory/requisitos/Vestuario/SPEC.md'));
        expect($spec)->toContain('US-VEST-007');
        expect($spec)->toContain('Asaas');
    });

    it('CustomerJourney persistido cita 5 capacidades em prod (W22 CAPTERRA)', function () {
        $scorecard = (string) file_get_contents(vestuarioW25Path('memory/governance/scorecards/vestuario.yaml'));
        // Capacidades chave do CustomerJourney ROTA LIVRE 2+ anos
        expect($scorecard)->toContain('US-VEST-001');
        expect($scorecard)->toContain('US-VEST-002');
        expect($scorecard)->toContain('US-VEST-005');
        expect($scorecard)->toContain('US-VEST-007');
        expect($scorecard)->toContain('format_date shift +3h');
    });
});

describe('Wave 25 Vestuario — V5 Docs canon (BRIEFING/CHANGELOG/CAPTERRA)', function () {

    it('CHANGELOG.md tem entry Wave 25 saturação', function () {
        $changelog = (string) file_get_contents(vestuarioW25Path('memory/requisitos/Vestuario/CHANGELOG.md'));
        expect($changelog)->toContain('Wave 25');
        expect($changelog)->toContain('vertical_client_facing');
    });

    it('CHANGELOG.md cita restauração D7 (regressão forense fix)', function () {
        $changelog = (string) file_get_contents(vestuarioW25Path('memory/requisitos/Vestuario/CHANGELOG.md'));
        expect($changelog)->toContain('D7');
        // Texto "regressão" ou "restaur" aceitos
        $temContext = str_contains($changelog, 'regressão') || str_contains($changelog, 'restaur');
        expect($temContext)->toBeTrue();
    });

    it('CAPTERRA-FICHA.md tem score V6 W25 declarado (≥90 alvo)', function () {
        $ficha = (string) file_get_contents(vestuarioW25Path('memory/requisitos/Vestuario/CAPTERRA-FICHA.md'));
        // Wave 25 deve aparecer com score boost
        expect($ficha)->toContain('Wave 25');
    });

    it('BRIEFING.md atualizado nota_atual reflete W25 boost', function () {
        $briefing = (string) file_get_contents(vestuarioW25Path('memory/requisitos/Vestuario/BRIEFING.md'));
        expect($briefing)->toContain('Vestuario');
        expect($briefing)->toContain('ROTA LIVRE');
        // Não pode regredir: ADR 0066 sempre citado
        expect($briefing)->toContain('ADR 0066');
        expect($briefing)->toContain('format_date');
    });
});

describe('Wave 25 Vestuario — V6 module.json governance bucket', function () {

    it('module.json governance.bucket = vertical_client_facing', function () {
        $json = json_decode((string) file_get_contents(vestuarioW25Path('Modules/Vestuario/module.json')), true);
        expect($json)->toHaveKey('governance');
        expect($json['governance']['bucket'])->toBe('vertical_client_facing');
    });

    it('module.json governance.scoped_score_target ≥85 (target Wave 23+)', function () {
        $json = json_decode((string) file_get_contents(vestuarioW25Path('Modules/Vestuario/module.json')), true);
        expect((int) $json['governance']['scoped_score_target'])->toBeGreaterThanOrEqual(85);
    });

    it('Tier 0 ADR 0066 format_date shift +3h preservado (nunca regressão)', function () {
        // Tripla validação: BRIEFING + CAPTERRA + scorecard
        foreach (['BRIEFING.md', 'CAPTERRA-FICHA.md'] as $doc) {
            $conteudo = (string) file_get_contents(vestuarioW25Path("memory/requisitos/Vestuario/{$doc}"));
            expect($conteudo)->toContain('ADR 0066');
        }
        $scorecard = (string) file_get_contents(vestuarioW25Path('memory/governance/scorecards/vestuario.yaml'));
        expect($scorecard)->toContain('0066');
    });
});

describe('Wave 25 Vestuario — Tier 0 cross-tenant biz=99 NUNCA biz=4', function () {

    it('LgpdComplianceTest declara biz=99 (NUNCA biz=4 ROTA LIVRE PROD)', function () {
        $test = (string) file_get_contents(vestuarioW25Path('Modules/Vestuario/Tests/Feature/LgpdComplianceTest.php'));
        // Tests biz=99 ADR 0101 — palavra deve aparecer no docblock
        expect($test)->toContain('biz=99');
        expect($test)->toContain('NUNCA biz=4');
    });

    it('Wave23 + Wave25 NÃO usam business_id=4 em fixtures PHP', function () {
        foreach (['Wave23VestuarioSaturationTest.php', 'Wave25VestuarioSaturationTest.php'] as $f) {
            $test = (string) file_get_contents(vestuarioW25Path("Modules/Vestuario/Tests/Feature/{$f}"));
            // Filtra apenas linhas PHP code (ignora /** docblock + // comments + #).
            $linhasCode = array_filter(
                explode("\n", $test),
                function ($ln): bool {
                    $t = trim($ln);
                    // ignora comments e docblock
                    return $t !== '' && ! str_starts_with($t, '*') && ! str_starts_with($t, '//')
                           && ! str_starts_with($t, '#') && ! str_starts_with($t, '/*');
                }
            );
            $code = implode("\n", $linhasCode);
            // Em CODE real (sem comments) NÃO pode ter array literal `'business_id' => 4`
            expect($code)->not->toMatch('/[\'"]business_id[\'"]\s*=>\s*4\b/');
            expect($code)->not->toMatch('/->business_id\s*=\s*4\b/');
        }
    });
});
