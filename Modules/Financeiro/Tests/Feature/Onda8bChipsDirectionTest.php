<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Onda 8b KB-9.75 — Polish visual Cowork: filter chips coloridos + direction arrows.
 *
 * Wagner 2026-05-18 sequência pós Onda 8 base — completa o gap entre "expectativa
 * vs realidade" (screenshot): chips com hue semântico + counts + direction arrows
 * canon (↘ entrada, ↗ saída).
 *
 * Cobre:
 *   - TAB_HUES Record com hue per status (verde 145 / rose 25 / azul 240)
 *   - countByTab helper computa lançamentos por categoria client-side
 *   - JSX <label className="fin-filter-cb"> substitui tabs flat shadcn
 *   - <span className="fin-filter-ct"> mostra counts dinâmicos
 *   - <fin-search-wrap> visual input search
 *   - <fin-density> 3-button toggle
 *   - Direction arrows com bg pill oklch semântico
 *
 * Tier 0 multi-tenant preservado (sem hardcode biz=N).
 */

const FIN_BASE_8B = __DIR__ . '/../../../../resources/js/Pages/Financeiro/Unificado';

describe('Onda 8b — TAB_HUES + countByTab helpers', function () {
    it('TAB_HUES record define hue oklch semântico por TabId (7 keys)', function () {
        $src = file_get_contents(FIN_BASE_8B . '/Index.tsx');
        expect($src)->toContain('const TAB_HUES: Record<TabId, number>');
        // 3 hues canon
        expect($src)->toMatch('/rec:\s*145/');         // verde A receber
        expect($src)->toMatch('/received:\s*145/');    // verde Recebidas
        expect($src)->toMatch('/pay:\s*25/');          // rose A pagar
        expect($src)->toMatch('/late:\s*25/');         // rose Atraso
        expect($src)->toContain('paid: 240');          // azul Pagas
    });

    it('countByTab helper computa lançamentos por TabId (7 cases)', function () {
        $src = file_get_contents(FIN_BASE_8B . '/Index.tsx');
        expect($src)->toContain('function countByTab(tabId: TabId, all: Lancamento[]): number');
        expect($src)->toContain("case 'all':");
        expect($src)->toContain("case 'open':");
        expect($src)->toContain("case 'rec':");
        expect($src)->toContain("case 'pay':");
        expect($src)->toContain("case 'received':");
        expect($src)->toContain("case 'paid':");
        expect($src)->toContain("case 'late':");
    });
});

describe('Onda 8b — filter chips canon Cowork', function () {
    it('Index.tsx tem <label fin-filter-cb> + fin-filter-cb-box + fin-filter-ct', function () {
        $src = file_get_contents(FIN_BASE_8B . '/Index.tsx');
        expect($src)->toContain("'fin-filter-cb' + (on ? ' on' : '')");
        expect($src)->toContain('fin-filter-cb-box');
        expect($src)->toContain('fin-filter-ct');
        // hue dinâmica via CSS var
        expect($src)->toContain("'--cb-hue' as string");
        // count > 0 visível só quando há resultado
        expect($src)->toContain('count > 0 &&');
    });

    it('toolbar Cowork substitui Card+CardContent shadcn antigos', function () {
        $src = file_get_contents(FIN_BASE_8B . '/Index.tsx');
        // Novo: classe fin-toolbar canon
        expect($src)->toContain('className="fin-toolbar mt-4"');
        // search visual + density 3-button preservados
        expect($src)->toContain('fin-search-wrap');
        expect($src)->toContain('fin-density');
        expect($src)->toContain('aria-label="Filtros por status"');
    });
});

describe('Onda 8b — direction arrows na tabela (entrada/saída)', function () {
    it('LinhaTabela renderiza arrow canon com bg pill semântico', function () {
        $src = file_get_contents(FIN_BASE_8B . '/Index.tsx');
        // ↘ entrada (receivable) verde 145
        expect($src)->toContain("isIn ? '↘' : '↗'");
        // oklch tokens semânticos
        expect($src)->toContain('oklch(0.94 0.06 145)');
        expect($src)->toContain('oklch(0.95 0.04 25)');
        // settled (recebido/pago) tem opacidade
        expect($src)->toContain('/ 0.6)');
    });

    it('aria-label "Entrada" / "Saída" presente (acessibilidade)', function () {
        $src = file_get_contents(FIN_BASE_8B . '/Index.tsx');
        expect($src)->toContain("aria-label={isIn ? 'Entrada' : 'Saída'}");
    });
});

describe('Onda 8b — preserva integrações + hooks Ondas 5/6/7', function () {
    it('NÃO toca Observer / Service / Subscription backend', function () {
        // Garante que polish frontend não introduziu modificação backend
        $src = file_get_contents(FIN_BASE_8B . '/Index.tsx');
        expect($src)->not->toContain('TituloAutoService');
        expect($src)->not->toContain('TransactionObserver');
    });

    it('Hooks useFinConferido + useFinComments continuam mounted', function () {
        $src = file_get_contents(FIN_BASE_8B . '/Index.tsx');
        expect($src)->toContain('useFinConferido()');
        expect($src)->toContain('useFinComments()');
    });

    it('Components Ondas 5/6/7 continuam imports + mounted', function () {
        $src = file_get_contents(FIN_BASE_8B . '/Index.tsx');
        expect($src)->toContain('FinChecklistFechamento');
        expect($src)->toContain('FinMonthDigest');
        expect($src)->toContain('FinCrossLinkify');
        expect($src)->toContain('FinAuditTrail');
        expect($src)->toContain('FinAnomalyDetector');
        expect($src)->toContain('FinPartyHistory');
    });
});
