<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Onda 10 — Fechamento canon 100% Cowork (Wagner 2026-05-18: "esta 95% eu quero 100%").
 *
 * Fecha os 5 gaps identificados entre template `_cowork-bundle/financeiro-app.jsx` (58k LOC)
 * e a tela /financeiro/unificado em prod:
 *   1. FinAgeing.tsx — barra ageing A receber (vencido / 0-30d / 31-60d / 61d+)
 *   2. FinSubNav.tsx — sub-nav horizontal (5 sub-rotas Cowork canon)
 *   3. Status "aberto" → label "Pendente" (Cowork STATUS_STYLES canon)
 *   4. FinEditPanel.tsx — form inline REAL (substitui preview readOnly V2.1)
 *   5. CSS canon escopado pros 2 novos componentes + ageing colors (s1-s4)
 *
 * Tier 0 multi-tenant preservado (pure-compute frontend, zero backend novo).
 */

const FIN_BASE_10 = __DIR__ . '/../../../../resources/js/Pages/Financeiro/Unificado';
const FIN_CSS_10 = __DIR__ . '/../../../../resources/css/fin-output.css';

describe('Onda 10 — FinAgeing (barra ageing A receber)', function () {
    it('FinAgeing.tsx exporta componente', function () {
        $src = file_get_contents(FIN_BASE_10 . '/_components/FinAgeing.tsx');
        expect($src)->toContain('export function FinAgeing');
    });

    it('Algoritmo: classifica delta por 4 buckets (late/d30/d60/d90)', function () {
        $src = file_get_contents(FIN_BASE_10 . '/_components/FinAgeing.tsx');
        expect($src)->toContain('buckets = { d30: 0, d60: 0, d90: 0, late: 0 }');
        expect($src)->toContain('if (delta < 0)');
        expect($src)->toContain('else if (delta <= 30)');
        expect($src)->toContain('else if (delta <= 60)');
    });

    it('Filtra apenas kind=receivable não-recebido (Cowork canon)', function () {
        $src = file_get_contents(FIN_BASE_10 . '/_components/FinAgeing.tsx');
        expect($src)->toContain("kind === 'receivable'");
        expect($src)->toContain("status !== 'recebido'");
    });

    it('Esconde quando total=0 (sem ageing)', function () {
        $src = file_get_contents(FIN_BASE_10 . '/_components/FinAgeing.tsx');
        expect($src)->toContain('if (k.total === 0) return null');
    });

    it('Renderiza 4 segments coloridos (s1-s4) com fin-ageing-bar', function () {
        $src = file_get_contents(FIN_BASE_10 . '/_components/FinAgeing.tsx');
        expect($src)->toContain('fin-ageing-bar');
        expect($src)->toContain("seg s1");
        expect($src)->toContain("seg s2");
        expect($src)->toContain("seg s3");
        expect($src)->toContain("seg s4");
    });
});

// Onda 10 — FinSubNav REMOVIDO (#4279 'limpa FinSubNav orfao'): o componente
// _components/FinSubNav.tsx foi deletado — a navheader migrou pro PageHeader canon v3.8
// e a sub-navegacao (5 sub-rotas) vive no PageHeader/tablist, nao num componente separado.
// Os 4 testes que liam FinSubNav.tsx (404) ficaram orfaos e falhavam de forma flaky.
// Sec.5: FinSubNav NAO volta como componente (navheader = PageHeader canon).

describe('Onda 10 — FinEditPanel (form inline REAL)', function () {
    it('FinEditPanel.tsx exporta componente', function () {
        $src = file_get_contents(FIN_BASE_10 . '/_components/FinEditPanel.tsx');
        expect($src)->toContain('export function FinEditPanel');
    });

    it('Usa Inertia useForm + PUT canon /financeiro/unificado/{id}', function () {
        $src = file_get_contents(FIN_BASE_10 . '/_components/FinEditPanel.tsx');
        expect($src)->toContain("useForm } from '@inertiajs/react'");
        expect($src)->toContain('form.put(`/financeiro/unificado/${lancamento.id}`');
    });

    it('Guard valor_mutavel (ADR fin-tech/0002) — não envia valor_total se imutável', function () {
        $src = file_get_contents(FIN_BASE_10 . '/_components/FinEditPanel.tsx');
        expect($src)->toContain('if (lancamento.valor_mutavel)');
        expect($src)->toContain('data.valor_total = form.data.valor_total');
        expect($src)->toContain('disabled={!lancamento.valor_mutavel}');
    });

    it('Diff visual "era X" inline em hints small', function () {
        $src = file_get_contents(FIN_BASE_10 . '/_components/FinEditPanel.tsx');
        expect($src)->toContain('era {brl(lancamento.valor)}');
        expect($src)->toContain('era {lancamento.vencimento_label}');
    });

    it('hasEdits guard desabilita botão Salvar sem mudanças', function () {
        $src = file_get_contents(FIN_BASE_10 . '/_components/FinEditPanel.tsx');
        expect($src)->toContain('const hasEdits =');
        expect($src)->toContain('disabled={form.processing || !hasEdits}');
    });
});

describe('Onda 10 — CSS canon (fin-ageing + fin-subnav)', function () {
    it('Tokens .fin-ageing-* mounted (4 segments coloridos)', function () {
        $css = file_get_contents(FIN_CSS_10);
        expect($css)->toContain('.fin-ageing');
        expect($css)->toContain('.fin-ageing-bar');
        expect($css)->toContain('.fin-ageing-bar .seg.s1');
        expect($css)->toContain('.fin-ageing-bar .seg.s2');
        expect($css)->toContain('.fin-ageing-bar .seg.s3');
        expect($css)->toContain('.fin-ageing-bar .seg.s4');
    });

    it('Tokens .fin-subnav-* mounted (sub-rotas horizontais)', function () {
        $css = file_get_contents(FIN_CSS_10);
        expect($css)->toContain('.fin-subnav');
        expect($css)->toContain('.fin-subnav-tab');
        expect($css)->toContain('.fin-subnav-tab.on');
    });

    it('Cores semânticas oklch dos segments: 145 verde / 60 amber / 280 roxo / 25 rose', function () {
        $css = file_get_contents(FIN_CSS_10);
        // s1 = 0-30d verde 145
        expect($css)->toContain('oklch(0.85 0.13 145)');
        // s2 = 31-60d amber 60-70
        expect($css)->toContain('oklch(0.85 0.13 70)');
        // s3 = 61d+ roxo 280
        expect($css)->toContain('oklch(0.75 0.13 280)');
        // s4 = late rose 25
        expect($css)->toContain('oklch(0.65 0.20 25)');
    });
});

describe('Onda 10 — wire-up Index.tsx (REVISADO 2026-05-18 Wagner: FinSubNav/FinAgeing removidos)', function () {
    it('Index.tsx NÃO importa mais FinEditPanel inline (FA-5: Editar virou botão → TituloEditSheet)', function () {
        $src = file_get_contents(FIN_BASE_10 . '/Index.tsx');
        // FA-5 2026-06-11 ([W] "2 abas + botão Editar campos" via AskUserQuestion): o FinEditPanel
        // inline (aba Editar da Onda 10) foi superado pelo botão "Editar campos" → TituloEditSheet
        // (editor completo). O componente FinEditPanel.tsx segue preservado em _components/.
        expect($src)->not->toContain("from './_components/FinEditPanel'");
        // FinSubNav e FinAgeing seguem removidos (Wagner duplicação)
        expect($src)->not->toContain("from './_components/FinSubNav'");
        expect($src)->not->toContain("from './_components/FinAgeing'");
    });

    it('FinSubNav NÃO renderizado no page (sidebar já cobre navegação)', function () {
        $src = file_get_contents(FIN_BASE_10 . '/Index.tsx');
        expect($src)->not->toContain('<FinSubNav');
    });

    it('FinAgeing NÃO renderizado no page (insight pertence ao drawer, não strip permanente)', function () {
        $src = file_get_contents(FIN_BASE_10 . '/Index.tsx');
        expect($src)->not->toContain('<FinAgeing');
    });

    it('Componentes preservados em _components/ pra fallback (não deletados)', function () {
        expect(file_exists(FIN_BASE_10 . '/_components/FinSubNav.tsx'))->toBeTrue();
        expect(file_exists(FIN_BASE_10 . '/_components/FinAgeing.tsx'))->toBeTrue();
    });

    it('Editar virou botão "Editar campos" → TituloEditSheet (supersede FinEditPanel inline · FA-5)', function () {
        $src = file_get_contents(FIN_BASE_10 . '/Index.tsx');
        expect($src)->toContain('Editar campos');
        expect($src)->toContain('setEditOpen(true)');
        // FinEditPanel inline saiu do Index (componente preservado em _components/ pra histórico)
        expect($src)->not->toContain('<FinEditPanel');
    });

    it('Status label "aberto" → "Pendente" (Cowork STATUS_STYLES canon)', function () {
        $src = file_get_contents(FIN_BASE_10 . '/Index.tsx');
        expect($src)->toContain("aberto: 'Pendente'");
    });
});
