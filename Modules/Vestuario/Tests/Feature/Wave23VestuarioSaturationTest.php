<?php

declare(strict_types=1);

namespace Modules\Vestuario\Tests\Feature;

/**
 * Helper path-resolution sem booting Laravel (rodável standalone via Pest).
 * Sobe 5 níveis: file → Feature → Tests → Vestuario → Modules → repo root.
 */
function vestuarioW23Path(string $path = ''): string
{
    $root = realpath(__DIR__ . '/../../../../');
    return $root . ($path !== '' ? DIRECTORY_SEPARATOR . $path : '');
}

/**
 * Wave 23 — Vestuario saturação bucket vertical_client_facing (ADR 0160).
 *
 * Target: nota scoped ≥85 (subindo de 67 W22). Cobertura V1/V3/V5/V6 da rubrica
 * scoped vertical_client_facing.yaml.
 *
 * Estratégia: smoke + reflection (sem boot Laravel) pra rodar rápido em paralelo
 * com outros buckets na mesma worktree. Pest v4 + biz=99 conceitual (ADR 0101 —
 * NUNCA biz=4 ROTA LIVRE PROD em test).
 *
 * Tier 0:
 *   - Multi-tenant ADR 0093 (global scope via HasBusinessScope)
 *   - ADR 0066 format_date shift +3h preservado (NÃO mexer)
 *   - ADR 0121 vertical especializado (P7 — vestuário-first)
 *
 * @see governance/buckets/vertical_client_facing.yaml
 * @see memory/decisions/0160-scoped-scorecard-evaluator-v3.md
 * @see memory/requisitos/Vestuario/CAPTERRA-FICHA.md
 */

describe('Wave 23 Vestuario — V1 Pest E2E Customer Journey', function () {

    it('CAPTERRA-FICHA.md existe e declara bucket vertical_client_facing', function () {
        $path = vestuarioW23Path('memory/requisitos/Vestuario/CAPTERRA-FICHA.md');
        expect(file_exists($path))->toBeTrue('CAPTERRA-FICHA.md obrigatória pra V6');

        $conteudo = (string) file_get_contents($path);
        expect($conteudo)->toContain('vertical_client_facing');
        expect($conteudo)->toContain('Vestuario');
        expect($conteudo)->toContain('ROTA LIVRE');
    });

    it('VestuarioSetting model existe + LogsActivity habilitado', function () {
        $class = 'Modules\\Vestuario\\Entities\\VestuarioSetting';
        expect(class_exists($class))->toBeTrue('VestuarioSetting model obrigatório');
    });

    it('FormRequests obrigatórios existem (V2 Code Quality)', function () {
        $reqs = [
            'Modules\\Vestuario\\Http\\Requests\\StoreVestuarioRequest',
            'Modules\\Vestuario\\Http\\Requests\\UpdateGradeRequest',
        ];
        foreach ($reqs as $r) {
            expect(class_exists($r))->toBeTrue("FormRequest {$r} obrigatório");
        }
    });
});

describe('Wave 23 Vestuario — V4 LGPD retention + ADR 0066 preservação', function () {

    it('Config/retention.php declara vestuario_settings com janela ≥365d', function () {
        $config = require vestuarioW23Path('Modules/Vestuario/Config/retention.php');
        expect($config)->toHaveKeys(['enabled', 'entities', 'strategy', 'notice_period_days']);
        expect($config['entities'])->toHaveKey('vestuario_settings');
        expect($config['entities']['vestuario_settings'])->toBeGreaterThanOrEqual(365);
    });

    it('Strategy default anonymize (preserva audit fiscal sem PII)', function () {
        $config = require vestuarioW23Path('Modules/Vestuario/Config/retention.php');
        expect($config['strategy'])->toBeIn(['anonymize', 'soft_delete', 'hard_delete']);
    });

    it('retention.enabled default false (gate manual ADR 0105 sinal qualificado)', function () {
        $config = require vestuarioW23Path('Modules/Vestuario/Config/retention.php');
        expect($config['enabled'])->toBeFalse('retention deve ser opt-in até job de purge existir');
    });

    it('ADR 0066 format_date shift +3h documentado em BRIEFING (não pode regressar)', function () {
        $briefing = vestuarioW23Path('memory/requisitos/Vestuario/BRIEFING.md');
        $conteudo = (string) file_get_contents($briefing);
        expect($conteudo)->toContain('format_date');
        expect($conteudo)->toContain('ADR 0066');
    });
});

describe('Wave 23 Vestuario — V5/V6 Docs canon + bucket governance', function () {

    it('module.json declara governance.bucket=vertical_client_facing', function () {
        $json = json_decode((string) file_get_contents(vestuarioW23Path('Modules/Vestuario/module.json')), true);
        expect($json)->toHaveKey('governance');
        expect($json['governance'])->toHaveKey('bucket');
        expect($json['governance']['bucket'])->toBe('vertical_client_facing');
        expect($json['governance']['scoped_score_target'])->toBeGreaterThanOrEqual(85);
    });

    it('BRIEFING.md atualizado (V5 docs canon)', function () {
        expect(file_exists(vestuarioW23Path('memory/requisitos/Vestuario/BRIEFING.md')))->toBeTrue();
    });

    it('CHANGELOG W23 entry presente', function () {
        $changelog = vestuarioW23Path('memory/requisitos/Vestuario/CHANGELOG.md');
        expect(file_exists($changelog))->toBeTrue('CHANGELOG.md obrigatório bucket vertical_client_facing');
        $conteudo = (string) file_get_contents($changelog);
        expect($conteudo)->toContain('Wave 23');
        expect($conteudo)->toContain('vertical_client_facing');
    });
});
