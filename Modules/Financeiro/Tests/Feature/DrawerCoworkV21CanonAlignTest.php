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

describe('Drawer 9.75 — Index.tsx 2 abas + Editar como botão (FA-5 · drawer-975)', function () {
    // FA-5 2026-06-11 ([W] "esse seria o projeto / isso é o esperado" + AskUserQuestion
    // "2 abas + botão Editar campos"): o protótipo Cowork 9.75 tem 2 abas (Detalhes · ✦ IA)
    // e move a edição pra um botão "Editar campos" ao lado de Conferir, que abre o
    // TituloEditSheet (editor completo). Substitui o canon V2.1 de 3 abas + FinEditPanel inline.
    it('drawerTab type = 2 abas: detalhes | ia (Editar saiu das abas)', function () {
        $src = file_get_contents(FIN_BASE_V21 . '/Index.tsx');
        expect($src)->toContain("useState<'detalhes' | 'ia'>");
        expect($src)->not->toContain("useState<'detalhes' | 'ia' | 'editar'>");
        expect($src)->not->toContain("setDrawerTab('editar')");
    });

    it('Nav renderiza 2 botões: Detalhes / ✦ IA', function () {
        $src = file_get_contents(FIN_BASE_V21 . '/Index.tsx');
        expect($src)->toContain("setDrawerTab('detalhes')");
        expect($src)->toContain("setDrawerTab('ia')");
        expect($src)->toContain('fin-drawer-tab-ai');
    });

    it('Editar virou botão "Editar campos" (abre TituloEditSheet via setEditOpen)', function () {
        $src = file_get_contents(FIN_BASE_V21 . '/Index.tsx');
        expect($src)->toContain('Editar campos');
        expect($src)->toContain('setEditOpen(true)');
    });

    it('Tab Detalhes usa fin-drawer-tab-ct + tag pra atrasado', function () {
        $src = file_get_contents(FIN_BASE_V21 . '/Index.tsx');
        expect($src)->toContain('fin-drawer-tab-ct');
        expect($src)->toContain('fin-drawer-tab-tag');
        expect($src)->toContain("selected.status === 'atrasado'");
    });

    it('Vínculos: chips estruturados derivados da descrição + nfe_numero (FA-5)', function () {
        $src = file_get_contents(FIN_BASE_V21 . '/Index.tsx');
        expect($src)->toContain('FinVinculosChips');
        expect($src)->toContain('FIN_XLINK_DEFS');
    });

    it('Ficha de identificação em fin-kv-card mantém os campos WR ([W] 2026-06-11)', function () {
        $src = file_get_contents(FIN_BASE_V21 . '/Index.tsx');
        expect($src)->toContain('fin-kv-card grid grid-cols-2');
        // Paridade WR preservada (não enxugou os campos)
        expect($src)->toContain('Valor em aberto');
    });

    it('Categoria editável inline (FinKVCategoriaInline); Canal segue read-only', function () {
        $src = file_get_contents(FIN_BASE_V21 . '/Index.tsx');
        expect($src)->toContain('FinKVCategoriaInline');
        expect($src)->toContain("selected.canal || 'manual'");
    });
});
