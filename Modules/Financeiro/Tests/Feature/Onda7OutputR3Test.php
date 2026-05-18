<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Onda 7 Financeiro KB-9.75 R3 Output + Cross-link — testes estruturais.
 *
 * Cobre:
 *   - 2 components novos: FinCrossLinkify (regex 6 padrões) + FinChecklistFechamento (12 passos)
 *   - CSS escopado em resources/css/fin-output.css importado APÓS fin-ia.css
 *   - Index.tsx wire-up (imports, trigger ☑ Fechamento no header,
 *     FinCrossLinkify no row + drawer, FinChecklistFechamento dialog state)
 *
 * Pure compute / localStorage (sem backend). Multi-tenant Tier 0 safe.
 *
 * Refs:
 *   - prototipo-ui/financeiro-output.jsx (canonical source)
 *   - resources/js/Pages/Sells/_components/SaleLinkifier.tsx (pattern Vendas Onda 3)
 */

const FIN_BASE_7 = __DIR__ . '/../../../../resources/js/Pages/Financeiro/Unificado';
const FIN_OUT_CSS = __DIR__ . '/../../../../resources/css/fin-output.css';
const FIN_INERTIA_CSS_7 = __DIR__ . '/../../../../resources/css/inertia.css';

describe('Onda 7 Financeiro R3 — 2 components existem', function () {
    it('FinCrossLinkify detecta 6 padrões de cross-reference', function () {
        $src = file_get_contents(FIN_BASE_7 . '/_components/FinCrossLinkify.tsx');
        expect($src)->toContain('export function FinCrossLinkify');
        // 6 kinds canon: venda, boleto, compra, os, receber, pagar
        expect($src)->toContain("kind: 'venda'");
        expect($src)->toContain("kind: 'boleto'");
        expect($src)->toContain("kind: 'compra'");
        expect($src)->toContain("kind: 'os'");
        expect($src)->toContain("kind: 'receber'");
        expect($src)->toContain("kind: 'pagar'");
        // Patterns regex canon
        expect($src)->toContain('#V-');
        expect($src)->toContain('#BL-');
        expect($src)->toContain('#PC-');
        expect($src)->toContain('#OS-');
        expect($src)->toContain('#R-');
        expect($src)->toContain('#P-');
        // Hrefs canon
        expect($src)->toContain('/sells/');
        expect($src)->toContain('/financeiro/boletos/');
        expect($src)->toContain('/compras/');
        expect($src)->toContain('/repair/job/');
        // Usa router.visit (Inertia SPA)
        expect($src)->toContain("from '@inertiajs/react'");
        expect($src)->toContain('router.visit(ref.href)');
    });

    it('FinChecklistFechamento define 12 passos em 4 grupos', function () {
        $src = file_get_contents(FIN_BASE_7 . '/_components/FinChecklistFechamento.tsx');
        expect($src)->toContain('export function FinChecklistFechamento');
        // 4 grupos canon
        expect($src)->toContain("group: 'reconcile'");
        expect($src)->toContain("group: 'review'");
        expect($src)->toContain("group: 'export'");
        expect($src)->toContain("group: 'communicate'");
        // Storage key prefix por mês (template string)
        expect($src)->toContain('oimpresso.financeiro.fechamento.');
        expect($src)->toContain('localStorage');
        // 12 step ids canônicos (conferindo subset característico)
        expect($src)->toContain("id: 'extrato-inter'");
        expect($src)->toContain("id: 'caixa-fisico'");
        expect($src)->toContain("id: 'anomaly-fix'");
        expect($src)->toContain("id: 'wagner-conferir'");
        expect($src)->toContain("id: 'dre-export'");
        expect($src)->toContain("id: 'reuniao-socio'");
    });
});

describe('Onda 7 Financeiro R3 — CSS escopado', function () {
    it('fin-output.css existe e escopa em .fin-curadoria', function () {
        $css = file_get_contents(FIN_OUT_CSS);
        expect($css)->toContain('.fin-curadoria .fin-xlink');
        expect($css)->toContain('.fin-curadoria .fin-checklist-dialog');
        expect($css)->toContain('.fin-curadoria .fin-checklist-progress');
        expect($css)->toContain('.fin-curadoria .fin-fechamento-trigger');
        // 6 cores cross-link
        expect($css)->toContain('.fin-xlink-venda');
        expect($css)->toContain('.fin-xlink-boleto');
        expect($css)->toContain('.fin-xlink-compra');
        expect($css)->toContain('.fin-xlink-os');
        expect($css)->toContain('.fin-xlink-receber');
        expect($css)->toContain('.fin-xlink-pagar');
    });

    it('inertia.css importa fin-output.css APÓS fin-ia.css', function () {
        $css = file_get_contents(FIN_INERTIA_CSS_7);
        expect($css)->toContain('@import "./fin-output.css"');
        $pos6 = strpos($css, '@import "./fin-ia.css"');
        $pos7 = strpos($css, '@import "./fin-output.css"');
        expect($pos6)->toBeLessThan($pos7);
    });
});

describe('Onda 7 Financeiro R3 — wire-up Index.tsx', function () {
    it('Index.tsx importa os 2 components da Onda 7', function () {
        $src = file_get_contents(FIN_BASE_7 . '/Index.tsx');
        expect($src)->toContain("from './_components/FinCrossLinkify'");
        expect($src)->toContain("from './_components/FinChecklistFechamento'");
    });

    it('Index.tsx tem trigger ☑ Fechamento no PageHeader action', function () {
        $src = file_get_contents(FIN_BASE_7 . '/Index.tsx');
        expect($src)->toContain('fin-fechamento-trigger');
        expect($src)->toContain('setChecklistOpen(true)');
        expect($src)->toContain('Fechamento');
    });

    it('Index.tsx renderiza FinCrossLinkify no row (LinhaTabela) e no drawer SheetTitle', function () {
        $src = file_get_contents(FIN_BASE_7 . '/Index.tsx');
        // Pelo menos 2 ocorrências (row + drawer)
        expect(substr_count($src, '<FinCrossLinkify'))->toBeGreaterThanOrEqual(2);
        expect($src)->toContain('text={row.descricao}');
        expect($src)->toContain('text={selected.descricao}');
    });

    it('Index.tsx renderiza FinChecklistFechamento dialog com state controlado', function () {
        $src = file_get_contents(FIN_BASE_7 . '/Index.tsx');
        expect($src)->toContain('<FinChecklistFechamento');
        expect($src)->toContain('open={checklistOpen}');
        expect($src)->toContain('onClose={() => setChecklistOpen(false)}');
        expect($src)->toContain('periodLabel={periodLabel}');
    });
});

describe('Onda 7 Financeiro R3 — charter v4', function () {
    it('Index.charter.md declara Onda 7 R3 features + charter v4', function () {
        $md = file_get_contents(FIN_BASE_7 . '/Index.charter.md');
        expect($md)->toContain('charter_version: 4');
        expect($md)->toContain('Onda 7 KB-9.75 R3 Output');
        expect($md)->toContain('FinCrossLinkify');
        expect($md)->toContain('FinChecklistFechamento');
        expect($md)->toContain('Ondas 5+6+7');
    });
});
