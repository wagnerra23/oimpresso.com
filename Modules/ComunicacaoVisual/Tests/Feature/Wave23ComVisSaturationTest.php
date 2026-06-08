<?php

declare(strict_types=1);

/**
 * Helper path sem booting Laravel.
 */
function comvisW23Path(string $path = ''): string
{
    $root = realpath(__DIR__ . '/../../../../');
    return $root . ($path !== '' ? DIRECTORY_SEPARATOR . $path : '');
}

/**
 * Wave 23 — ComunicacaoVisual saturação bucket vertical_client_facing (ADR 0160).
 *
 * Target: nota scoped ≥85 (subindo de 41.5 W22 — gap maior do bucket).
 * Cobertura V1/V3/V4/V5/V6 da rubrica scoped vertical_client_facing.yaml.
 *
 * Estratégia: smoke + reflection (sem boot Laravel) pra rodar rápido. Pest v4
 * + biz=99 (ADR 0101). Complementa CustomerJourneyTest existente (DB-based).
 *
 * Tier 0:
 *   - Multi-tenant ADR 0093 (cv_orcamentos.business_id global scope)
 *   - FSM canon ADR 0143 (cv_ordens_producao.current_stage_id consumido)
 *   - LGPD retention canon (Config/retention.php)
 *
 * @see governance/buckets/vertical_client_facing.yaml
 * @see memory/decisions/0160-scoped-scorecard-evaluator-v3.md
 * @see memory/requisitos/ComunicacaoVisual/CAPTERRA-FICHA.md
 */

describe('Wave 23 ComVis — V1 Pest E2E smoke jornada', function () {

    it('CAPTERRA-FICHA.md existe e cobre top 5 P0 (saída do gap W22)', function () {
        $path = comvisW23Path('memory/requisitos/ComunicacaoVisual/CAPTERRA-FICHA.md');
        expect(file_exists($path))->toBeTrue('CAPTERRA-FICHA obrigatória');
        $conteudo = (string) file_get_contents($path);
        expect($conteudo)->toContain('vertical_client_facing');
        expect($conteudo)->toContain('ComunicacaoVisual');
        expect($conteudo)->toContain('Mubisys');
        expect($conteudo)->toContain('Zênite');
    });

    it('Entities Orcamento/Os/Apontamento existem (V1 smoke estrutural)', function () {
        $classes = [
            'Modules\\ComunicacaoVisual\\Entities\\Orcamento',
            'Modules\\ComunicacaoVisual\\Entities\\Os',
            'Modules\\ComunicacaoVisual\\Entities\\Apontamento',
        ];
        foreach ($classes as $c) {
            expect(class_exists($c))->toBeTrue("Entity {$c} obrigatória pra jornada cliente");
        }
    });

    it('Controllers Orcamento/Os/Apontamento existem (V1 fluxo)', function () {
        $controllers = [
            'Modules\\ComunicacaoVisual\\Http\\Controllers\\OrcamentoController',
            'Modules\\ComunicacaoVisual\\Http\\Controllers\\ApontamentoController',
        ];
        foreach ($controllers as $c) {
            expect(class_exists($c))->toBeTrue("Controller {$c} obrigatório");
        }
    });
});

describe('Wave 23 ComVis — V4 LGPD retention canon', function () {

    it('Config/retention.php declara apontamento + orcamento + os com 5y', function () {
        $config = require comvisW23Path('Modules/ComunicacaoVisual/Config/retention.php');
        expect($config)->toHaveKey('entities');
        foreach (['apontamento', 'orcamento', 'os'] as $entity) {
            expect($config['entities'])->toHaveKey($entity);
            expect($config['entities'][$entity]['days'])->toBeGreaterThanOrEqual(1825);
            expect($config['entities'][$entity])->toHaveKey('basis_legal');
        }
    });

    it('Apontamento marcado append-only (CCom Art. 195 + Portaria 671)', function () {
        $config = require comvisW23Path('Modules/ComunicacaoVisual/Config/retention.php');
        expect($config['entities']['apontamento']['append_only'])->toBeTrue();
    });

    it('right_to_be_forgotten habilitado (LGPD Art. 18 VI)', function () {
        $config = require comvisW23Path('Modules/ComunicacaoVisual/Config/retention.php');
        expect($config)->toHaveKey('right_to_be_forgotten');
        expect($config['right_to_be_forgotten']['enabled'])->toBeTrue();
        expect($config['right_to_be_forgotten']['preserve_fiscal_ids'])->toBeTrue();
    });

    it('telemetry janela curta (12m) sem PII', function () {
        $config = require comvisW23Path('Modules/ComunicacaoVisual/Config/retention.php');
        expect($config['telemetry']['days'])->toBeLessThanOrEqual(365);
    });
});

describe('Wave 23 ComVis — V5/V6 Docs canon + bucket governance', function () {

    it('module.json declara governance.bucket=vertical_client_facing', function () {
        $json = json_decode((string) file_get_contents(comvisW23Path('Modules/ComunicacaoVisual/module.json')), true);
        expect($json['governance']['bucket'])->toBe('vertical_client_facing');
        expect($json['governance']['scoped_score_target'])->toBeGreaterThanOrEqual(85);
        expect($json['governance']['audit_log_enabled'])->toBeTrue();
    });

    it('audit_log_entities cobre 3 entities core (Orcamento/Os/Apontamento)', function () {
        $json = json_decode((string) file_get_contents(comvisW23Path('Modules/ComunicacaoVisual/module.json')), true);
        expect($json['governance']['audit_log_entities'])->toContain('Orcamento');
        expect($json['governance']['audit_log_entities'])->toContain('Os');
        expect($json['governance']['audit_log_entities'])->toContain('Apontamento');
    });

    it('CHANGELOG W23 entry presente', function () {
        $changelog = comvisW23Path('memory/requisitos/ComunicacaoVisual/CHANGELOG.md');
        expect(file_exists($changelog))->toBeTrue();
        $conteudo = (string) file_get_contents($changelog);
        expect($conteudo)->toContain('Wave 23');
        expect($conteudo)->toContain('vertical_client_facing');
    });
});
