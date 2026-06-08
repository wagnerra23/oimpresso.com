<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Drawer Cowork v2.1 — canon align (Anthropic API design fetch 2026-05-18).
 *
 * Wagner reaplicou pacote v2 do prototipo-ui-patch/vendas-financeiro-completo/.
 * Bundle real (styles.css 9054 LOC) revelou diffs:
 *   - active state usa border-bottom 2px verde 145 (não box bg azul 240)
 *   - IA hue = 295 (não 280)
 *   - 3ª aba canônica "✎ Editar" amber 60 (substitui Sheet separado)
 *   - .fin-drawer-tab-ct (count) substitui .fin-drawer-tab-badge
 *   - .fin-drawer-tab-tag (red dot pra alerta)
 *
 * Multi-tenant Tier 0 preservado (UI-only, zero backend).
 */

const FIN_BASE_V21 = __DIR__ . '/../../../../resources/js/Pages/Financeiro/Unificado';
const FIN_CSS_V21 = __DIR__ . '/../../../../resources/css/fin-output.css';

describe('Drawer V2.1 — CSS canon align (Cowork bundle)', function () {
    it('Active state: border-bottom 2px verde 145 (não box bg azul)', function () {
        $css = file_get_contents(FIN_CSS_V21);
        // Pega seção tabs canônica
        expect($css)->toContain("border-bottom-color: oklch(0.55 0.13 145)");
        // NÃO usa mais oklch 240 como active bg
        expect($css)->not->toMatch('/\.fin-drawer-tab\.on\s*\{[^}]*background:\s*oklch\(0\.97\s+0\.02\s+240/');
    });

    it('IA hue = 295 (não 280) — canon Cowork', function () {
        $css = file_get_contents(FIN_CSS_V21);
        expect($css)->toContain('.fin-drawer-tab-ai');
        expect($css)->toContain('oklch(0.42 0.13 295)');
        expect($css)->toContain('oklch(0.55 0.13 295)');
    });

    it('3ª aba canon .fin-drawer-tab-edit (amber 60 + has-edits state)', function () {
        $css = file_get_contents(FIN_CSS_V21);
        expect($css)->toContain('.fin-drawer-tab-edit');
        expect($css)->toContain('oklch(0.42 0.13 60)');
        expect($css)->toContain('.fin-drawer-tab-edit.on');
        expect($css)->toContain('.fin-drawer-tab-edit.has-edits');
    });

    it('Count badge canon .fin-drawer-tab-ct (não fin-drawer-tab-badge)', function () {
        $css = file_get_contents(FIN_CSS_V21);
        expect($css)->toContain('.fin-drawer-tab-ct');
        // ui-monospace + pill background
        expect($css)->toContain('ui-monospace');
    });

    it('Red dot canon .fin-drawer-tab-tag (alerta visual)', function () {
        $css = file_get_contents(FIN_CSS_V21);
        expect($css)->toContain('.fin-drawer-tab-tag');
        expect($css)->toContain('oklch(0.55 0.18 25)'); // rose
    });

    it('Edit panel canon: gradient amber + animate finEditOpen + grid 3 col', function () {
        $css = file_get_contents(FIN_CSS_V21);
        expect($css)->toContain('@keyframes finEditOpen');
        expect($css)->toContain('linear-gradient(135deg, oklch(0.98 0.025 60)');
        expect($css)->toContain('.fin-edit-grid');
        expect($css)->toContain('grid-template-columns: 1fr 1fr 1fr');
        expect($css)->toContain('.fin-edit-wide');
        expect($css)->toContain('.fin-edit-close');
    });

    it('Nav fin-drawer-tabs flush com bg-2 (não margin-bottom -6px velho)', function () {
        $css = file_get_contents(FIN_CSS_V21);
        // Canon: padding 0 18px + gap 0 + bg neutro
        expect($css)->toMatch('/\.fin-drawer-tabs\s*\{[^}]*padding:\s*0\s+18px/');
        expect($css)->toMatch('/\.fin-drawer-tabs\s*\{[^}]*gap:\s*0/');
    });
});

describe('Drawer V2.1 — Index.tsx 3 abas + Editar inline', function () {
    it('drawerTab type unifies 3 abas: detalhes | ia | editar', function () {
        $src = file_get_contents(FIN_BASE_V21 . '/Index.tsx');
        expect($src)->toContain("useState<'detalhes' | 'ia' | 'editar'>");
    });

    it('Nav renderiza 3 botões: Detalhes / ✦ IA / ✎ Editar', function () {
        $src = file_get_contents(FIN_BASE_V21 . '/Index.tsx');
        expect($src)->toContain("setDrawerTab('detalhes')");
        expect($src)->toContain("setDrawerTab('ia')");
        expect($src)->toContain("setDrawerTab('editar')");
        expect($src)->toContain('fin-drawer-tab-ai');
        expect($src)->toContain('fin-drawer-tab-edit');
    });

    it('Tab Detalhes usa fin-drawer-tab-ct (não badge antigo) + tag pra atrasado', function () {
        $src = file_get_contents(FIN_BASE_V21 . '/Index.tsx');
        expect($src)->toContain('fin-drawer-tab-ct');
        expect($src)->toContain('fin-drawer-tab-tag');
        expect($src)->toContain("selected.status === 'atrasado'");
    });

    it('Tab Editar respeita valor_mutavel (disabled + tooltip ADR fin-tech/0002)', function () {
        $src = file_get_contents(FIN_BASE_V21 . '/Index.tsx');
        expect($src)->toContain('disabled={!selected.valor_mutavel}');
        expect($src)->toContain('ADR fin-tech/0002');
    });

    it('Aba Editar renderiza fin-edit-panel inline (não Sheet)', function () {
        $src = file_get_contents(FIN_BASE_V21 . '/Index.tsx');
        $start = strpos($src, "drawerTab === 'editar'");
        $end = strpos($src, '</Sheet>');
        $editPanel = substr($src, $start, $end - $start);
        expect($editPanel)->toContain('fin-edit-panel');
        expect($editPanel)->toContain('fin-edit-h');
        expect($editPanel)->toContain('fin-edit-grid');
        expect($editPanel)->toContain('fin-edit-footer');
        // Botão "Abrir formulário completo" delega ao TituloEditSheet existente (preserva validação)
        expect($editPanel)->toContain('Abrir formulário completo');
    });

    it('Caso valor_mutavel=false mostra empty state com mensagem ADR', function () {
        $src = file_get_contents(FIN_BASE_V21 . '/Index.tsx');
        expect($src)->toContain('!selected.valor_mutavel');
        expect($src)->toContain('Valor não-mutável');
    });
});
