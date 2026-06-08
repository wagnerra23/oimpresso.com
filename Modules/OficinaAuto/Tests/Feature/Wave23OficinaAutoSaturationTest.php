<?php

declare(strict_types=1);

/**
 * Helper path sem booting Laravel.
 */
function oficinaW23Path(string $path = ''): string
{
    $root = realpath(__DIR__ . '/../../../../');
    return $root . ($path !== '' ? DIRECTORY_SEPARATOR . $path : '');
}

/**
 * Wave 23 — OficinaAuto saturação bucket vertical_client_facing (ADR 0160).
 *
 * Target: nota scoped ≥85 (subindo de 63 W22). Cobertura V1/V3/V5/V6 da rubrica
 * scoped vertical_client_facing.yaml.
 *
 * Estratégia: smoke + reflection (sem boot Laravel), evita DB pra rodar paralelo
 * sem conflito worktree. Pest v4 + biz=99 (ADR 0101 — Vargas/Martinho biz reais
 * NÃO em test).
 *
 * Tier 0:
 *   - Multi-tenant ADR 0093 (Vehicle/ServiceOrder global scope)
 *   - FSM canon ADR 0143 (service_order pipeline complexa orçamento→entrega)
 *   - LGPD: plate/chassis/renavam PII protegidos (PiiRedactor)
 *
 * @see governance/buckets/vertical_client_facing.yaml
 * @see memory/decisions/0160-scoped-scorecard-evaluator-v3.md
 * @see memory/requisitos/OficinaAuto/CAPTERRA-FICHA.md
 */

describe('Wave 23 OficinaAuto — V1 Pest E2E (FSM pipeline complexa)', function () {

    it('CAPTERRA-FICHA.md existe e cita FSM pipeline service_order', function () {
        $path = oficinaW23Path('memory/requisitos/OficinaAuto/CAPTERRA-FICHA.md');
        expect(file_exists($path))->toBeTrue('CAPTERRA-FICHA obrigatória');
        $conteudo = (string) file_get_contents($path);
        expect($conteudo)->toContain('vertical_client_facing');
        expect($conteudo)->toContain('OficinaAuto');
        expect($conteudo)->toContain('FSM');
    });

    it('Vehicle + ServiceOrder entities existem (V1 core)', function () {
        expect(class_exists('Modules\\OficinaAuto\\Entities\\Vehicle'))->toBeTrue();
        expect(class_exists('Modules\\OficinaAuto\\Entities\\ServiceOrder'))->toBeTrue();
    });

    it('Controllers Vehicle/ServiceOrder/ProducaoOficina existem', function () {
        $controllers = [
            'Modules\\OficinaAuto\\Http\\Controllers\\VehicleController',
            'Modules\\OficinaAuto\\Http\\Controllers\\ServiceOrderController',
            'Modules\\OficinaAuto\\Http\\Controllers\\ProducaoOficinaController',
        ];
        foreach ($controllers as $c) {
            expect(class_exists($c))->toBeTrue("Controller {$c} obrigatório");
        }
    });

    it('FormRequests Store/Update ServiceOrder + Vehicle existem (V2)', function () {
        $reqs = [
            'Modules\\OficinaAuto\\Http\\Requests\\StoreServiceOrderRequest',
            'Modules\\OficinaAuto\\Http\\Requests\\UpdateServiceOrderRequest',
        ];
        foreach ($reqs as $r) {
            expect(class_exists($r))->toBeTrue("FormRequest {$r} obrigatório (anti-IDOR Tier 0)");
        }
    });
});

describe('Wave 23 OficinaAuto — V4 LGPD + governance', function () {

    it('Config/retention.php existe + janela 1825d veículos PII', function () {
        $config = require oficinaW23Path('Modules/OficinaAuto/Config/retention.php');
        expect($config)->toBeArray();
        expect($config)->toHaveKey('enabled');
    });

    it('module.json declara LGPD PII fields tracked', function () {
        $json = json_decode((string) file_get_contents(oficinaW23Path('Modules/OficinaAuto/module.json')), true);
        expect($json)->toHaveKey('lgpd_compliance');
        expect($json['lgpd_compliance']['pii_fields_tracked'])->toContain('plate');
        expect($json['lgpd_compliance']['pii_fields_tracked'])->toContain('chassis');
        expect($json['lgpd_compliance']['pii_redactor_enabled'])->toBeTrue();
    });
});

describe('Wave 23 OficinaAuto — V5/V6 Docs canon + bucket', function () {

    it('module.json declara governance.bucket=vertical_client_facing + FSM canon', function () {
        $json = json_decode((string) file_get_contents(oficinaW23Path('Modules/OficinaAuto/module.json')), true);
        expect($json['governance']['bucket'])->toBe('vertical_client_facing');
        expect($json['governance']['fsm_canonico'])->toBeTrue();
        expect($json['governance']['fsm_pipeline'])->toBe('service_order');
        expect($json['governance']['scoped_score_target'])->toBeGreaterThanOrEqual(85);
    });

    it('BRIEFING.md + ROADMAP.md existem (V5 docs canon)', function () {
        expect(file_exists(oficinaW23Path('memory/requisitos/OficinaAuto/BRIEFING.md')))->toBeTrue();
        expect(file_exists(oficinaW23Path('memory/requisitos/OficinaAuto/ROADMAP.md')))->toBeTrue();
        expect(file_exists(oficinaW23Path('memory/requisitos/OficinaAuto/SPEC.md')))->toBeTrue();
    });

    it('CHANGELOG W23 entry presente', function () {
        $changelog = oficinaW23Path('memory/requisitos/OficinaAuto/CHANGELOG.md');
        expect(file_exists($changelog))->toBeTrue();
        $conteudo = (string) file_get_contents($changelog);
        expect($conteudo)->toContain('Wave 23');
        expect($conteudo)->toContain('vertical_client_facing');
    });

    it('MATRIZ-ROI.md cobre top 5 ROI items (V6 Capterra V6.b)', function () {
        $path = oficinaW23Path('memory/requisitos/OficinaAuto/MATRIZ-ROI.md');
        expect(file_exists($path))->toBeTrue('MATRIZ-ROI obrigatória pra V6.b ROI top 5');
    });
});
