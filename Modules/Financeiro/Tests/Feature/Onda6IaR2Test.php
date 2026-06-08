<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Onda 6 Financeiro KB-9.75 R2 IA — testes estruturais.
 *
 * Cobre:
 *   - 3 components novos em resources/js/Pages/Financeiro/Unificado/_components/
 *     (FinAnomalyDetector, FinPartyHistory, FinMonthDigest)
 *   - CSS escopado em resources/css/fin-ia.css importado por inertia.css
 *   - Index.tsx wire-up (imports, FinMonthDigest acima tabela, FinAnomaly +
 *     FinPartyHistory no drawer)
 *
 * Pure compute (sem backend, sem LLM real). Multi-tenant Tier 0 safe.
 *
 * Refs:
 *   - memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md
 *   - prototipo-ui/financeiro-ai.jsx (canonical source)
 */

const FIN_BASE_6 = __DIR__ . '/../../../../resources/js/Pages/Financeiro/Unificado';
const FIN_IA_CSS = __DIR__ . '/../../../../resources/css/fin-ia.css';
const FIN_INERTIA_CSS_6 = __DIR__ . '/../../../../resources/css/inertia.css';

describe('Onda 6 Financeiro R2 IA — 3 components existem', function () {
    it('FinAnomalyDetector exporta finAnomalyDetect + component, threshold 25% + 3 severities', function () {
        $src = file_get_contents(FIN_BASE_6 . '/_components/FinAnomalyDetector.tsx');
        expect($src)->toContain('export function finAnomalyDetect');
        expect($src)->toContain('export function FinAnomalyDetector');
        expect($src)->toContain('THRESHOLD_PCT = 25');
        expect($src)->toContain("'high'");
        expect($src)->toContain("'medium'");
        expect($src)->toContain("'low'");
        expect($src)->toContain('severity:');
    });

    it('FinPartyHistory exporta finPartyHistory + component, isNew + isRecurrent', function () {
        $src = file_get_contents(FIN_BASE_6 . '/_components/FinPartyHistory.tsx');
        expect($src)->toContain('export function finPartyHistory');
        expect($src)->toContain('export function FinPartyHistory');
        expect($src)->toContain('isNew');
        expect($src)->toContain('isRecurrent');
        expect($src)->toContain('mine.length >= 3');
        expect($src)->toContain('onTimePct');
    });

    it('FinMonthDigest exporta buildMonthDigest + component com 4 cards', function () {
        $src = file_get_contents(FIN_BASE_6 . '/_components/FinMonthDigest.tsx');
        expect($src)->toContain('export function buildMonthDigest');
        expect($src)->toContain('export function FinMonthDigest');
        // 4 cards canon: cashIn, cashOut, net, late
        expect($src)->toContain('cashIn');
        expect($src)->toContain('cashOut');
        expect($src)->toContain('net:');
        expect($src)->toContain('late:');
        // Top party
        expect($src)->toContain('topPartyIn');
        expect($src)->toContain('topPartyOut');
        // Conferido% integration
        expect($src)->toContain('conferidoPct');
    });
});

describe('Onda 6 Financeiro R2 IA — CSS escopado', function () {
    it('fin-ia.css existe e escopa em .fin-curadoria (mesma wrapper Onda 5)', function () {
        $css = file_get_contents(FIN_IA_CSS);
        expect($css)->toContain('.fin-curadoria .fin-anomaly');
        expect($css)->toContain('.fin-curadoria .fin-party-history');
        expect($css)->toContain('.fin-curadoria .fin-digest');
        // 3 severities anomaly
        expect($css)->toContain('.fin-anomaly-sev-high');
        expect($css)->toContain('.fin-anomaly-sev-medium');
        expect($css)->toContain('.fin-anomaly-sev-low');
    });

    it('inertia.css importa fin-ia.css apos fin-curadoria.css', function () {
        $css = file_get_contents(FIN_INERTIA_CSS_6);
        expect($css)->toContain('@import "./fin-ia.css"');
        // Ordem: Onda 5 (fin-curadoria) antes de Onda 6 (fin-ia)
        $pos5 = strpos($css, '@import "./fin-curadoria.css"');
        $pos6 = strpos($css, '@import "./fin-ia.css"');
        expect($pos5)->toBeLessThan($pos6);
    });
});

describe('Onda 6 Financeiro R2 IA — wire-up Index.tsx', function () {
    it('Index.tsx importa os 3 components da Onda 6', function () {
        $src = file_get_contents(FIN_BASE_6 . '/Index.tsx');
        expect($src)->toContain("from './_components/FinAnomalyDetector'");
        expect($src)->toContain("from './_components/FinPartyHistory'");
        expect($src)->toContain("from './_components/FinMonthDigest'");
    });

    it('Index.tsx renderiza FinMonthDigest acima da tabela com props corretos', function () {
        $src = file_get_contents(FIN_BASE_6 . '/Index.tsx');
        expect($src)->toContain('<FinMonthDigest');
        expect($src)->toContain('lancamentos={lancamentos}');
        expect($src)->toContain('kpis={kpis}');
        expect($src)->toContain('periodLabel={periodLabel}');
    });

    it('Index.tsx drawer renderiza FinAnomalyDetector + FinPartyHistory', function () {
        $src = file_get_contents(FIN_BASE_6 . '/Index.tsx');
        expect($src)->toContain('<FinAnomalyDetector');
        expect($src)->toContain('<FinPartyHistory');
        // Ambos recebem all={lancamentos} pra compute histórico
        expect($src)->toContain('all={lancamentos}');
    });
});

describe('Onda 6 Financeiro R2 IA — charter v3', function () {
    it('Index.charter.md declara Onda 6 R2 features + charter v3', function () {
        $md = file_get_contents(FIN_BASE_6 . '/Index.charter.md');
        expect($md)->toContain('charter_version: 3');
        expect($md)->toContain('Onda 6 KB-9.75 R2 IA');
        expect($md)->toContain('FinAnomalyDetector');
        expect($md)->toContain('FinPartyHistory');
        expect($md)->toContain('FinMonthDigest');
        expect($md)->toContain('Ondas 5+6');
    });
});
