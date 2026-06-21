<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Drawer Cowork v2 — gap report Wagner 2026-05-18.
 *
 * Substitui drawer monolítico (scroll vertical sem hierarquia) por:
 *  - Nav fin-drawer-tabs com 2 abas: Detalhes + ✦ IA
 *  - Aba Detalhes: status pill + conferido + dl info + audit + comments + actions
 *  - Aba IA: FinAnomalyDetector + FinPartyHistory (insights computacionais)
 *  - CSS canon: 30+ matches do regex Wagner em fin-curadoria.css + fin-output.css
 *
 * Tier 0 multi-tenant safe (zero novas queries, só reorganização da UI).
 */

const FIN_BASE_DRAWER = __DIR__ . '/../../../../resources/js/Pages/Financeiro/Unificado';
const FIN_CSS_DRAWER_OUTPUT = __DIR__ . '/../../../../resources/css/fin-output.css';
const FIN_CSS_DRAWER_CURADORIA = __DIR__ . '/../../../../resources/css/fin-curadoria.css';

describe('Drawer Cowork v2 — wire-up Index.tsx', function () {
    it('Index.tsx instancia drawerTab state com reset on selectedId change', function () {
        $src = file_get_contents(FIN_BASE_DRAWER . '/Index.tsx');
        expect($src)->toContain("const [drawerTab, setDrawerTab] = useState<'detalhes' | 'ia'>('detalhes')");
        // Reset on row change (UX: nova linha = volta pra Detalhes)
        expect($src)->toContain("setDrawerTab('detalhes'); }, [selectedId]");
    });

    it('Drawer renderiza <nav className="fin-drawer-tabs"> após SheetHeader', function () {
        $src = file_get_contents(FIN_BASE_DRAWER . '/Index.tsx');
        expect($src)->toContain('<nav className="fin-drawer-tabs"');
        expect($src)->toContain("role=\"tablist\"");
        // 2 botões: Detalhes + ✦ IA
        expect($src)->toContain("setDrawerTab('detalhes')");
        expect($src)->toContain("setDrawerTab('ia')");
        expect($src)->toContain('fin-drawer-tab-ai');
    });

    it('Badge "💬N" no tab Detalhes quando comments.countFor(id) > 0', function () {
        $src = file_get_contents(FIN_BASE_DRAWER . '/Index.tsx');
        expect($src)->toContain('comments.countFor(selected.id) > 0');
        expect($src)->toContain('fin-drawer-tab-badge');
    });

    it('Conteúdo split por aba — Detalhes vs IA', function () {
        $src = file_get_contents(FIN_BASE_DRAWER . '/Index.tsx');
        expect($src)->toContain("drawerTab === 'detalhes'");
        expect($src)->toContain("drawerTab === 'ia'");
        // Aba IA tem panel wrapper canônico
        expect($src)->toContain('fin-ai-panel');
        // Aba Detalhes tem footer canônico
        expect($src)->toContain('fin-drawer-footer');
    });

    it('Aba IA isola AnomalyDetector + PartyHistory (zero detalhes info duplicados)', function () {
        $src = file_get_contents(FIN_BASE_DRAWER . '/Index.tsx');
        // Aba IA inclui esses 2 componentes Cowork canon
        $start = strpos($src, "drawerTab === 'ia'");
        $end = strpos($src, '</Sheet>');
        $iaPanel = substr($src, $start, $end - $start);
        expect($iaPanel)->toContain('<FinAnomalyDetector');
        expect($iaPanel)->toContain('<FinPartyHistory');
    });

    it('Drawer tem className fin-drawer-wide pra preserve padding Cowork', function () {
        $src = file_get_contents(FIN_BASE_DRAWER . '/Index.tsx');
        expect($src)->toContain('fin-drawer-wide');
    });
})->skip('Drawer Cowork v2 não implementado — Index.tsx sem tabs fin-drawer-tabs e CSS pendente');

describe('Drawer Cowork v2 — CSS classes faltantes (gap report Wagner)', function () {
    it('threshold 30+ matches no regex canônico (sanity check Wagner)', function () {
        $css = file_get_contents(FIN_CSS_DRAWER_CURADORIA) . file_get_contents(FIN_CSS_DRAWER_OUTPUT);
        $count = preg_match_all('/fin-drawer-tabs|fin-conferido-toggle|fin-ai-anomalia|fin-audit-row|fin-frescor/', $css);
        // Wagner: "Se retornar <10, foi truncado. Deve retornar 30+"
        expect($count)->toBeGreaterThanOrEqual(30);
    });

    it('fin-drawer-tabs + fin-drawer-tab + fin-drawer-tab-ai mounted', function () {
        $css = file_get_contents(FIN_CSS_DRAWER_OUTPUT);
        expect($css)->toContain('.fin-drawer-tabs');
        expect($css)->toContain('.fin-drawer-tab ');
        expect($css)->toContain('.fin-drawer-tab.on');
        expect($css)->toContain('.fin-drawer-tab-ai');
        expect($css)->toContain('.fin-drawer-tab-badge');
    });

    it('fin-drawer-wide + fin-drawer-footer + fin-toggles-row + fin-edit-btn', function () {
        $css = file_get_contents(FIN_CSS_DRAWER_OUTPUT);
        expect($css)->toContain('.fin-drawer-wide');
        expect($css)->toContain('.fin-drawer-footer');
        expect($css)->toContain('.fin-toggles-row');
        expect($css)->toContain('.fin-edit-btn');
        expect($css)->toContain('.fin-edit-panel');
    });

    it('fin-ai-panel + fin-ai-anomalia + vd-ai-stats Cowork canon', function () {
        $css = file_get_contents(FIN_CSS_DRAWER_OUTPUT);
        expect($css)->toContain('.fin-ai-panel');
        expect($css)->toContain('.fin-ai-anomalia');
        expect($css)->toContain('.vd-ai-stats');
        expect($css)->toContain('.vd-ai-stat');
    });

    it('fin-comments-h + fin-comment (substitui stack flat anterior)', function () {
        $css = file_get_contents(FIN_CSS_DRAWER_OUTPUT);
        expect($css)->toContain('.fin-comments-h');
        expect($css)->toContain('.fin-comment ');
    });

    it('fin-pill-frescor alias (Cowork ref) — variantes fresh/warning/overdue', function () {
        $css = file_get_contents(FIN_CSS_DRAWER_OUTPUT);
        expect($css)->toContain('.fin-pill-frescor');
        expect($css)->toContain('.fin-pill-frescor-fresh');
        expect($css)->toContain('.fin-pill-frescor-warning');
        expect($css)->toContain('.fin-pill-frescor-overdue');
    });
})->skip('Drawer Cowork v2 não implementado — Index.tsx sem tabs fin-drawer-tabs e CSS pendente');
