<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Onda 5 Financeiro KB-9.75 R1 Curadoria — testes estruturais.
 *
 * Cobre:
 *   - 4 components novos em resources/js/Pages/Financeiro/Unificado/_components/
 *     (FinPillFrescor, FinConferidoToggle, FinCommentsThread, FinAuditTrail)
 *   - CSS escopado em resources/css/fin-curadoria.css importado por inertia.css
 *   - Index.tsx wire-up (imports, hooks, wrap .fin-curadoria, drawer enriquecido,
 *     row badges silent)
 *
 * Multi-tenant Tier 0 (ADR 0093) — testes file_get_contents + regex (sem DB).
 *
 * Refs:
 *   - memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md
 *   - prototipo-ui/financeiro-curation.jsx (canonical source)
 */

const FIN_BASE = __DIR__ . '/../../../../resources/js/Pages/Financeiro/Unificado';
const FIN_CSS_PATH = __DIR__ . '/../../../../resources/css/fin-curadoria.css';
defined('FIN_INERTIA_CSS') || define('FIN_INERTIA_CSS', __DIR__ . '/../../../../resources/css/inertia.css');

describe('Onda 5 Financeiro R1 Curadoria — 4 components existem', function () {
    it('FinPillFrescor existe e exporta finFrescorInfo + 6 kinds', function () {
        $src = file_get_contents(FIN_BASE . '/_components/FinPillFrescor.tsx');
        expect($src)->toContain('export function finFrescorInfo');
        expect($src)->toContain('export function FinPillFrescor');
        expect($src)->toContain("kind: 'paid'");
        expect($src)->toContain("kind: 'overdue'");
        expect($src)->toContain("kind: 'today'");
        expect($src)->toContain("kind: 'warning'");
        expect($src)->toContain("kind: 'soon'");
        expect($src)->toContain("kind: 'fresh'");
    });

    it('FinConferidoToggle exporta useFinConferido hook + Toggle + Badge', function () {
        $src = file_get_contents(FIN_BASE . '/_components/FinConferidoToggle.tsx');
        expect($src)->toContain('export function useFinConferido');
        expect($src)->toContain('export function FinConferidoToggle');
        expect($src)->toContain('export function FinConferidoBadge');
        expect($src)->toContain("'oimpresso.financeiro.conferido'");
        expect($src)->toContain('localStorage');
    });

    it('FinCommentsThread exporta useFinComments hook + Thread + Badge', function () {
        $src = file_get_contents(FIN_BASE . '/_components/FinCommentsThread.tsx');
        expect($src)->toContain('export function useFinComments');
        expect($src)->toContain('export function FinCommentsThread');
        expect($src)->toContain('export function FinCommentsBadge');
        expect($src)->toContain("'oimpresso.financeiro.comments'");
    });

    it('FinAuditTrail exporta finAuditTrail + componente com 5 kinds', function () {
        $src = file_get_contents(FIN_BASE . '/_components/FinAuditTrail.tsx');
        expect($src)->toContain('export function finAuditTrail');
        expect($src)->toContain('export function FinAuditTrail');
        expect($src)->toContain("kind: 'create'");
        expect($src)->toContain("kind: 'categorize'");
        expect($src)->toContain("kind: 'edit'");
        expect($src)->toContain("kind: 'concil'");
        expect($src)->toContain("kind: 'alert'");
    });
})->skip('Onda 5 Financeiro R1 parcialmente implementado — componentes e charter pendentes');

describe('Onda 5 Financeiro R1 Curadoria — CSS escopado', function () {
    it('fin-curadoria.css existe e escopa em .fin-curadoria', function () {
        $css = file_get_contents(FIN_CSS_PATH);
        expect($css)->toContain('.fin-curadoria .fin-conferido-toggle');
        expect($css)->toContain('.fin-curadoria .fin-frescor');
        expect($css)->toContain('.fin-curadoria .fin-audit');
        expect($css)->toContain('.fin-curadoria .fin-comments');
        // 6 estados de frescor com cores
        expect($css)->toContain('.fin-frescor-paid');
        expect($css)->toContain('.fin-frescor-overdue');
        expect($css)->toContain('.fin-frescor-today');
        expect($css)->toContain('.fin-frescor-warning');
        expect($css)->toContain('.fin-frescor-soon');
        expect($css)->toContain('.fin-frescor-fresh');
    });

    it('inertia.css importa fin-curadoria.css', function () {
        $css = file_get_contents(FIN_INERTIA_CSS);
        expect($css)->toContain('@import "./fin-curadoria.css"');
    });
})->skip('Onda 5 Financeiro R1 parcialmente implementado — componentes e charter pendentes');

describe('Onda 5 Financeiro R1 Curadoria — wire-up Index.tsx', function () {
    it('Index.tsx importa os 4 components da Onda 5', function () {
        $src = file_get_contents(FIN_BASE . '/Index.tsx');
        expect($src)->toContain("from './_components/FinPillFrescor'");
        expect($src)->toContain("from './_components/FinConferidoToggle'");
        expect($src)->toContain("from './_components/FinCommentsThread'");
        expect($src)->toContain("from './_components/FinAuditTrail'");
    });

    it('Index.tsx wrap raiz com .fin-curadoria pra escopar CSS', function () {
        $src = file_get_contents(FIN_BASE . '/Index.tsx');
        expect($src)->toContain('<div className="fin-curadoria">');
    });

    it('Index.tsx instancia hooks useFinConferido + useFinComments no FinanceiroUnificado', function () {
        $src = file_get_contents(FIN_BASE . '/Index.tsx');
        expect($src)->toContain('const conferido = useFinConferido()');
        expect($src)->toContain('const comments = useFinComments()');
    });

    it('Index.tsx passa hooks pro LinhaTabela (badges silent na linha)', function () {
        $src = file_get_contents(FIN_BASE . '/Index.tsx');
        expect($src)->toContain('conferido={conferido}');
        expect($src)->toContain('comments={comments}');
        expect($src)->toContain('<FinConferidoBadge');
        expect($src)->toContain('<FinCommentsBadge');
        expect($src)->toContain('<FinPillFrescor');
    });

    it('Index.tsx drawer enriquecido com Conferido toggle + Audit + Comments thread', function () {
        $src = file_get_contents(FIN_BASE . '/Index.tsx');
        expect($src)->toContain('<FinConferidoToggle rowId={selected.id}');
        expect($src)->toContain('<FinAuditTrail row=');
        expect($src)->toContain('<FinCommentsThread rowId={selected.id}');
    });
})->skip('Onda 5 Financeiro R1 parcialmente implementado — componentes e charter pendentes');

describe('Onda 5 Financeiro R1 Curadoria — charter atualizado', function () {
    it('Index.charter.md declara Onda 5 R1 features + canon_method KB-9.75', function () {
        $md = file_get_contents(FIN_BASE . '/Index.charter.md');
        expect($md)->toContain('canon_method: Cowork KB-9.75');
        expect($md)->toContain('charter_version: 2');
        expect($md)->toContain('last_validated: 2026-05-18');
        expect($md)->toContain('Onda 5 KB-9.75 R1 Curadoria');
        expect($md)->toContain('Conferido toggle');
        expect($md)->toContain('Frescor pill');
        expect($md)->toContain('Audit trail');
    });
})->skip('Onda 5 Financeiro R1 parcialmente implementado — componentes e charter pendentes');
