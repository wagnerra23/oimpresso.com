<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Onda 7c KB-9.75 — FinTranscriptPDF + useFinFavs hook.
 *
 * Substitui ausência de impressão estruturada (Eliana hoje screenshot/printscreen)
 * por folha jurídica imprimível com @media print + favoritos pessoais
 * (localStorage, multi-tenant-safe via prefixo business).
 *
 * Cobre:
 *   - useFinFavs.tsx: hook localStorage prefixo oimpresso.fin.favs.<biz>
 *   - FinTranscriptPDF.tsx: overlay fullscreen + @print isolation + folha A4
 *   - CSS: .fin-transcript-* + @media print + .fin-fav-pin
 *   - Wire-up Index.tsx: state transcriptOpen + FinFavPin + atalho B
 *
 * Multi-tenant Tier 0 preservado (zero backend, prefixo localStorage por business).
 */

const FIN_BASE_7C = __DIR__ . '/../../../../resources/js/Pages/Financeiro/Unificado';
const FIN_OUTPUT_CSS_7C = __DIR__ . '/../../../../resources/css/fin-output.css';
const FIN_COWORK_CSS_7C = __DIR__ . '/../../../../resources/css/fin-cowork.css';

describe('Onda 7c — useFinFavs hook (favoritos pessoais localStorage)', function () {
    it('useFinFavs.tsx exports hook + FinFavPin component', function () {
        $src = file_get_contents(FIN_BASE_7C . '/_components/useFinFavs.tsx');
        expect($src)->toContain('export function useFinFavs');
        expect($src)->toContain('export function FinFavPin');
    });

    it('prefixa localStorage com business key (Tier 0 isolation)', function () {
        $src = file_get_contents(FIN_BASE_7C . '/_components/useFinFavs.tsx');
        expect($src)->toContain("STORAGE_PREFIX = 'oimpresso.fin.favs.'");
        // O hook recebe businessKey e usa no key — não pode haver tenant leak
        expect($src)->toContain('businessKey');
        expect($src)->toContain('STORAGE_VERSION');
    });

    it('expõe API completa: toggle/has/clear/count + favs Set', function () {
        $src = file_get_contents(FIN_BASE_7C . '/_components/useFinFavs.tsx');
        expect($src)->toContain('interface UseFinFavsApi');
        expect($src)->toContain('toggle:');
        expect($src)->toContain('has:');
        expect($src)->toContain('clear:');
        expect($src)->toContain('count:');
    });
});

describe('Onda 7c — FinTranscriptPDF (folha jurídica imprimível)', function () {
    it('FinTranscriptPDF exporta componente + props canon', function () {
        $src = file_get_contents(FIN_BASE_7C . '/_components/FinTranscriptPDF.tsx');
        expect($src)->toContain('export function FinTranscriptPDF');
        expect($src)->toContain('open:');
        expect($src)->toContain('onClose:');
        expect($src)->toContain('lancamentos:');
        expect($src)->toContain('periodLabel:');
        expect($src)->toContain('onlyFavs');
    });

    it('Atalho Esc fecha + window.print integrado', function () {
        $src = file_get_contents(FIN_BASE_7C . '/_components/FinTranscriptPDF.tsx');
        expect($src)->toContain("e.key === 'Escape'");
        expect($src)->toContain('window.print()');
    });

    it('Folha A4: header empresa + tabela + tfoot totals + 2 assinaturas', function () {
        $src = file_get_contents(FIN_BASE_7C . '/_components/FinTranscriptPDF.tsx');
        expect($src)->toContain('fin-transcript-page');
        expect($src)->toContain('fin-transcript-h');
        expect($src)->toContain('fin-transcript-tbl');
        expect($src)->toContain('Entradas');
        expect($src)->toContain('Saídas');
        expect($src)->toContain('Saldo líquido');
        expect($src)->toContain('fin-transcript-sig'); // assinaturas
    });

    it('Filtro onlyFavs limita lançamentos quando Set não-vazio', function () {
        $src = file_get_contents(FIN_BASE_7C . '/_components/FinTranscriptPDF.tsx');
        expect($src)->toContain('onlyFavs');
        expect($src)->toContain('onlyFavs.has(l.id)');
    });
});

describe('Onda 7c — CSS escopado @media print isolation', function () {
    it('Tokens .fin-transcript-* mounted em fin-output.css', function () {
        $css = file_get_contents(FIN_OUTPUT_CSS_7C);
        expect($css)->toContain('.fin-transcript-overlay');
        expect($css)->toContain('.fin-transcript-page');
        expect($css)->toContain('.fin-transcript-tbl');
        expect($css)->toContain('.fin-transcript-bar');
        expect($css)->toContain('.fin-fav-pin');
    });

    it('@media print isola .fin-transcript-page (esconde overlay/bar/header app)', function () {
        $css = file_get_contents(FIN_OUTPUT_CSS_7C);
        expect($css)->toContain('@media print');
        expect($css)->toContain('body * { visibility: hidden; }');
        // Folha visível no print
        expect($css)->toContain('.fin-transcript-overlay,');
        // @page A4
        expect($css)->toContain('@page { size: A4');
    });

    it('Badge .fin-btn-badge no fin-cowork.css (count favoritos no botão Imprimir)', function () {
        $css = file_get_contents(FIN_COWORK_CSS_7C);
        expect($css)->toContain('.fin-curadoria .fin-btn-badge');
    });
});

describe('Onda 7c — wire-up Index.tsx', function () {
    it('Index.tsx importa FinTranscriptPDF + useFinFavs + FinFavPin', function () {
        $src = file_get_contents(FIN_BASE_7C . '/Index.tsx');
        expect($src)->toContain("from './_components/FinTranscriptPDF'");
        expect($src)->toContain("from './_components/useFinFavs'");
        expect($src)->toContain('useFinFavs');
        expect($src)->toContain('FinFavPin');
    });

    it('Index.tsx instancia transcriptOpen + transcriptOnlyFavs + favs', function () {
        $src = file_get_contents(FIN_BASE_7C . '/Index.tsx');
        expect($src)->toContain('const [transcriptOpen, setTranscriptOpen]');
        expect($src)->toContain('const [transcriptOnlyFavs, setTranscriptOnlyFavs]');
        expect($src)->toContain('const favs = useFinFavs(');
    });

    it('Atalho B toggle fav da linha selecionada', function () {
        $src = file_get_contents(FIN_BASE_7C . '/Index.tsx');
        expect($src)->toContain("e.key === 'b'");
        expect($src)->toContain('favs.toggle(selectedId)');
    });

    it('LinhaTabela renderiza FinFavPin com isFav prop', function () {
        $src = file_get_contents(FIN_BASE_7C . '/Index.tsx');
        expect($src)->toContain('isFav');
        expect($src)->toContain('<FinFavPin');
        expect($src)->toContain('favs.has(r.id)');
    });

    it('Botão Imprimir no header + entradas no command palette', function () {
        $src = file_get_contents(FIN_BASE_7C . '/Index.tsx');
        expect($src)->toContain('📄 Imprimir');
        expect($src)->toContain('setTranscriptOpen(true)');
        // Palette: 1 entrada normal + 1 condicional (só favoritos)
        expect($src)->toContain('Imprimir período');
        expect($src)->toContain('Imprimir só favoritos');
    });

    it('Drawer detalhe inclui botão Favoritar (★/☆)', function () {
        $src = file_get_contents(FIN_BASE_7C . '/Index.tsx');
        expect($src)->toContain('favs.toggle(selected.id)');
        expect($src)->toContain('Favoritado');
    });

    it('FinTranscriptPDF mount com props canônicas', function () {
        $src = file_get_contents(FIN_BASE_7C . '/Index.tsx');
        expect($src)->toContain('<FinTranscriptPDF');
        expect($src)->toContain('onlyFavs={transcriptOnlyFavs ? favs.favs : null}');
    });

    it('Footer-tips mostra atalho B + count de favoritos quando >0', function () {
        $src = file_get_contents(FIN_BASE_7C . '/Index.tsx');
        expect($src)->toContain('<kbd>B</kbd> favoritar linha');
        expect($src)->toContain('favs.count > 0');
    });
});
