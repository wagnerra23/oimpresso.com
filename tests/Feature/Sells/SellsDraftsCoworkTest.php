<?php

declare(strict_types=1);

/**
 * Pest — Onda Cowork Sells/Drafts — estrutura visual scoped.
 *
 * Cobertura estrutural via file_get_contents (Pest browser cobre interativo
 * quando estabilizar). Foca em garantir que:
 *  - CSS sells-cowork-drafts.css existe + scope correto + tokens locais
 *  - inertia.css importa sells-cowork-drafts.css
 *  - Drafts.tsx wrappa div outer com AMBAS classes (família + extensão)
 *  - Marcadores canônicos (US-SELL-DRAFTS-COWORK + ADR refs) presentes
 *  - Charter Drafts.charter.md preservado intacto
 *  - Componentes específicos da página (badge "Rascunho", chip idade, CTA) renderizam
 *  - Anti-pattern: NÃO tocar Index.tsx (área proibida do agent vizinho)
 *
 * Refs:
 *  - resources/css/sells-cowork-drafts.css (novo)
 *  - resources/js/Pages/Sells/Drafts.tsx (modificado wrapper)
 *  - resources/js/Pages/Sells/Drafts.charter.md (preservado)
 *  - memory/requisitos/_DesignSystem/RUNBOOK-onda-cowork.md
 *  - tests/Feature/Sells/SellsQuotationsCoworkTest.php (pattern espelho)
 */

const DRAFTS_TSX_PATH = 'resources/js/Pages/Sells/Drafts.tsx';
const DRAFTS_CHARTER_PATH = 'resources/js/Pages/Sells/Drafts.charter.md';
const DRAFTS_CSS_PATH = 'resources/css/sells-cowork-drafts.css';
const DRAFTS_INERTIA_CSS_PATH = 'resources/css/inertia.css';
const DRAFTS_INDEX_TSX_PATH = 'resources/js/Pages/Sells/Index.tsx';
const DRAFTS_FAMILY_CSS_PATH = 'resources/css/sells-cowork.css';

function draftsRead(string $rel): string
{
    return file_get_contents(base_path($rel));
}

// ─── Arquivos existem (smoke estrutural) ──────────────────────────────

it('CSS sells-cowork-drafts.css existe', function () {
    expect(file_exists(base_path(DRAFTS_CSS_PATH)))->toBeTrue();
});

it('Drafts.tsx ainda existe (não removido)', function () {
    expect(file_exists(base_path(DRAFTS_TSX_PATH)))->toBeTrue();
});

it('Drafts.charter.md preservado (não removido nem deslocado)', function () {
    expect(file_exists(base_path(DRAFTS_CHARTER_PATH)))->toBeTrue();
});

// ─── CSS scoped + tokens locais ──────────────────────────────────────

it('CSS sells-cowork-drafts.css scopa sob .sells-cowork-drafts', function () {
    $source = draftsRead(DRAFTS_CSS_PATH);
    expect($source)->toContain('.sells-cowork-drafts {');
});

it('CSS define tokens locais neutral (badge rascunho), continue (CTA azul), age (chip idade)', function () {
    $source = draftsRead(DRAFTS_CSS_PATH);
    expect($source)
        ->toContain('--vd-draft-neutral:')
        ->toContain('--vd-draft-neutral-soft:')
        ->toContain('--vd-draft-continue:')
        ->toContain('--vd-draft-age-fresh:')
        ->toContain('--vd-draft-age-aging:')
        ->toContain('--vd-draft-age-stale:');
});

it('CSS define classes principais (badge, continue-btn, age chip 3 variants)', function () {
    $source = draftsRead(DRAFTS_CSS_PATH);
    expect($source)
        ->toContain('.sells-cowork-drafts .vd-draft-badge')
        ->toContain('.sells-cowork-drafts .vd-draft-continue-btn')
        ->toContain('.sells-cowork-drafts .vd-draft-age')
        ->toContain('.vd-draft-age.fresh')
        ->toContain('.vd-draft-age.aging')
        ->toContain('.vd-draft-age.stale');
});

it('CSS NÃO redefine tokens da família Index (--accent, --surface, --row-h preservados na família)', function () {
    $source = draftsRead(DRAFTS_CSS_PATH);
    // Tokens locais OK (--vd-draft-*); mas a página NÃO redeclara variáveis canon
    // da família Index (--accent, --surface, --row-h, --shadow-pop etc.) — reusa via cascata.
    expect($source)
        ->not->toContain('--accent:')
        ->not->toContain('--surface:')
        ->not->toContain('--row-h:')
        ->not->toContain('--shadow-pop:')
        ->not->toContain('--sb-bg:');
});

// ─── inertia.css importa novo CSS ────────────────────────────────────

it('inertia.css importa sells-cowork-drafts.css', function () {
    $source = draftsRead(DRAFTS_INERTIA_CSS_PATH);
    expect($source)->toContain('@import "./sells-cowork-drafts.css"');
});

it('inertia.css preserva imports da família Index (não removeu sells-cowork.css)', function () {
    $source = draftsRead(DRAFTS_INERTIA_CSS_PATH);
    expect($source)
        ->toContain('@import "./sells-cowork.css"')
        ->toContain('@import "./sells-cowork-ia.css"')
        ->toContain('@import "./sells-cowork-curadoria.css"')
        ->toContain('@import "./sells-cowork-distribuicao.css"')
        ->toContain('@import "./sells-cowork-quotations.css"');
});

// ─── Drafts.tsx wrappa com escopo cumulativo ─────────────────────────

it('Drafts.tsx wrappa div outer com AMBAS classes (sells-cowork + sells-cowork-drafts)', function () {
    $source = draftsRead(DRAFTS_TSX_PATH);
    // Match em className só — descarta menções em comentário (ignora linhas que começam com //)
    $lines = explode("\n", $source);
    $hasWrapper = false;
    foreach ($lines as $line) {
        $trimmed = ltrim($line);
        if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) {
            continue;
        }
        // Procura className que contém AMBAS classes na mesma string literal
        if (preg_match('/className=["`][^"`]*\bsells-cowork\b[^"`]*\bsells-cowork-drafts\b[^"`]*["`]/', $line)) {
            $hasWrapper = true;
            break;
        }
    }
    expect($hasWrapper)->toBeTrue();
});

it('Drafts.tsx contém marcador US-SELL-DRAFTS-COWORK', function () {
    $source = draftsRead(DRAFTS_TSX_PATH);
    expect($source)->toContain('US-SELL-DRAFTS-COWORK');
});

it('Drafts.tsx renderiza badge "Rascunho" (vd-draft-badge)', function () {
    $source = draftsRead(DRAFTS_TSX_PATH);
    expect($source)
        ->toContain('vd-draft-badge')
        ->toContain('Rascunho');
});

it('Drafts.tsx renderiza chip de idade (vd-draft-age) com helpers draftAge*', function () {
    $source = draftsRead(DRAFTS_TSX_PATH);
    expect($source)
        ->toContain('vd-draft-age')
        ->toContain('draftAgeDays')
        ->toContain('draftAgeTone')
        ->toContain('draftAgeLabel');
});

it('Drafts.tsx renderiza botão "Continuar venda" (vd-draft-continue-btn) com link edit', function () {
    $source = draftsRead(DRAFTS_TSX_PATH);
    expect($source)
        ->toContain('vd-draft-continue-btn')
        ->toContain('Continuar venda')
        ->toContain('/sells/${r.id}/edit');
});

it('Drafts.tsx preserva funcionalidade core (props, fetch, atalhos N/Esc)', function () {
    $source = draftsRead(DRAFTS_TSX_PATH);
    expect($source)
        ->toContain('export default function SellsDrafts')
        ->toContain('fetchDrafts')
        ->toContain("if (e.key === 'n')")
        ->toContain("if (e.key === 'Escape')")
        ->toContain('SellsDrafts.layout');
});

it('Drafts.tsx preserva referência ADR canônicas (104, 149, 110, 093)', function () {
    $source = draftsRead(DRAFTS_TSX_PATH);
    expect($source)
        ->toContain('ADR 0104')
        ->toContain('ADR 0149')
        ->toContain('ADR 0110')
        ->toContain('ADR 0093');
});

// ─── Charter preservado ──────────────────────────────────────────────

it('Drafts.charter.md preserva Mission + page header (frontmatter intacto)', function () {
    $source = draftsRead(DRAFTS_CHARTER_PATH);
    expect($source)
        ->toContain('page: /sells/drafts')
        ->toContain('component: resources/js/Pages/Sells/Drafts.tsx')
        ->toContain('# Page Charter — /sells/drafts')
        ->toContain('## Mission');
});

// ─── Anti-pattern: áreas proibidas intactas ──────────────────────────

it('Index.tsx NÃO foi tocado por esta Onda (área proibida do agent vizinho)', function () {
    $source = draftsRead(DRAFTS_INDEX_TSX_PATH);
    // Garante que Index NÃO recebeu marcador específico de Drafts (sinal de invasão)
    expect($source)
        ->not->toContain('US-SELL-DRAFTS-COWORK')
        ->not->toContain('sells-cowork-drafts');
});

it('sells-cowork.css canon família Index intacta (não recebeu tokens rascunho)', function () {
    $source = draftsRead(DRAFTS_FAMILY_CSS_PATH);
    expect($source)
        ->not->toContain('--vd-draft-neutral')
        ->not->toContain('--vd-draft-continue')
        ->not->toContain('vd-draft-badge');
});
