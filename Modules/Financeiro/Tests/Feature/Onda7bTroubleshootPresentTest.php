<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Onda 7b KB-9.75 — FinTroubleshooter + FinPresentationMode.
 *
 * Substitui alert() de "▶ Apresentar" por componente real fullscreen + adiciona
 * "? Resolver" button no footer com 4 árvores de decisão (extrato não bate /
 * boleto pago 2x / fornecedor cobrou errado / NFe rejeitada).
 *
 * Cobre:
 *   - FinTroubleshooter.tsx: 4 árvores FIN_TROUBLES + componente dialog
 *   - FinPresentationMode.tsx: 3 views (overview / parties / categories) + atalhos
 *   - CSS escopado em fin-output.css (Onda 7 wrapper)
 *   - Wire-up Index.tsx: 2 states + render dialogs + trigger button no footer
 *
 * Multi-tenant Tier 0 preservado (zero backend).
 */

const FIN_BASE_7B = __DIR__ . '/../../../../resources/js/Pages/Financeiro/Unificado';
const FIN_OUTPUT_CSS_7B = __DIR__ . '/../../../../resources/css/fin-output.css';

describe('Onda 7b — FinTroubleshooter (4 árvores de decisão)', function () {
    it('FinTroubleshooter.tsx exporta FIN_TROUBLES com 4 árvores canônicas', function () {
        $src = file_get_contents(FIN_BASE_7B . '/_components/FinTroubleshooter.tsx');
        expect($src)->toContain('export const FIN_TROUBLES');
        expect($src)->toContain("'tr-extrato-nao-bate'");
        expect($src)->toContain("'tr-boleto-pago-2x'");
        expect($src)->toContain("'tr-fornecedor-cobrou-errado'");
        expect($src)->toContain("'tr-nfe-rejeitada-fin'");
    });

    it('FinTroubleshooterDialog component renderiza lista + flow + resolution', function () {
        $src = file_get_contents(FIN_BASE_7B . '/_components/FinTroubleshooter.tsx');
        expect($src)->toContain('export function FinTroubleshooterDialog');
        // 3 estados: list (4 cards) → flow (Q/A) → resolution (fix)
        expect($src)->toContain('fin-trouble-list');
        expect($src)->toContain('fin-trouble-flow');
        expect($src)->toContain('fin-trouble-resolution');
        // Sugestão automática via suggestedId
        expect($src)->toContain('suggestedId');
    });

    it('FinTroubleButton trigger pra footer/drawer', function () {
        $src = file_get_contents(FIN_BASE_7B . '/_components/FinTroubleshooter.tsx');
        expect($src)->toContain('export function FinTroubleButton');
        expect($src)->toContain('fin-trouble-trigger');
        expect($src)->toContain('fin-trouble-count');
    });
});

describe('Onda 7b — FinPresentationMode (fullscreen reunião)', function () {
    it('FinPresentationMode component com 3 views', function () {
        $src = file_get_contents(FIN_BASE_7B . '/_components/FinPresentationMode.tsx');
        expect($src)->toContain('export function FinPresentationMode');
        expect($src)->toContain("type ViewMode = 'overview' | 'parties' | 'categories'");
    });

    it('Atalhos teclado canon (Esc fecha, 1/2/3 muda view)', function () {
        $src = file_get_contents(FIN_BASE_7B . '/_components/FinPresentationMode.tsx');
        expect($src)->toContain("e.key === 'Escape'");
        expect($src)->toContain("e.key === '1'");
        expect($src)->toContain("e.key === '2'");
        expect($src)->toContain("e.key === '3'");
    });

    it('Views renderizam: hero KPI big + parties table + categories table', function () {
        $src = file_get_contents(FIN_BASE_7B . '/_components/FinPresentationMode.tsx');
        expect($src)->toContain('fin-present-hero');
        expect($src)->toContain('fin-present-grid');
        expect($src)->toContain('Top 10 contrapartes');
        expect($src)->toContain('Top 10 categorias');
    });
});

describe('Onda 7b — CSS escopado em fin-output.css', function () {
    it('Tokens CSS Troubleshooter + Presentation mounted', function () {
        $css = file_get_contents(FIN_OUTPUT_CSS_7B);
        // Troubleshooter
        expect($css)->toContain('.fin-curadoria .fin-trouble-backdrop');
        expect($css)->toContain('.fin-curadoria .fin-trouble-dialog');
        expect($css)->toContain('.fin-curadoria .fin-trouble-card');
        expect($css)->toContain('.fin-curadoria .fin-trouble-flow');
        expect($css)->toContain('.fin-curadoria .fin-trouble-fix');
        // Presentation Mode (NOT escopado em .fin-curadoria — é fullscreen)
        expect($css)->toContain('.fin-present');
        expect($css)->toContain('.fin-present-hero');
        expect($css)->toContain('.fin-present-grid');
        expect($css)->toContain('.fin-present-table');
    });
});

describe('Onda 7b — wire-up Index.tsx', function () {
    it('Index.tsx importa FinTroubleshooter + FinPresentationMode', function () {
        $src = file_get_contents(FIN_BASE_7B . '/Index.tsx');
        expect($src)->toContain("from './_components/FinTroubleshooter'");
        expect($src)->toContain("from './_components/FinPresentationMode'");
    });

    it('Index.tsx instancia troubleOpen + presentOpen states', function () {
        $src = file_get_contents(FIN_BASE_7B . '/Index.tsx');
        expect($src)->toContain('const [troubleOpen, setTroubleOpen]');
        expect($src)->toContain('const [presentOpen, setPresentOpen]');
    });

    it('Botão ▶ Apresentar agora abre PresentationMode (não alert)', function () {
        $src = file_get_contents(FIN_BASE_7B . '/Index.tsx');
        expect($src)->toContain('onClick={() => setPresentOpen(true)}');
        // alert removido
        expect($src)->not->toContain("alert('Apresentar:");
    });

    it('FinTroubleButton renderizado no footer com onClick setTroubleOpen', function () {
        $src = file_get_contents(FIN_BASE_7B . '/Index.tsx');
        expect($src)->toContain('<FinTroubleButton');
        expect($src)->toContain('setTroubleOpen(true)');
    });

    it('FinTroubleshooterDialog + FinPresentationMode renderizados', function () {
        $src = file_get_contents(FIN_BASE_7B . '/Index.tsx');
        expect($src)->toContain('<FinTroubleshooterDialog');
        expect($src)->toContain('<FinPresentationMode');
        // PresentationMode recebe kpis + lancamentos + periodLabel + businessName
        expect($src)->toContain('kpis={kpis}');
        expect($src)->toContain('lancamentos={lancamentos}');
        expect($src)->toContain('periodLabel={periodLabel}');
    });
});
