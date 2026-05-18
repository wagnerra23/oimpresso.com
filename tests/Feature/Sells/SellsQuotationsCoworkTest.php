<?php

declare(strict_types=1);

/**
 * Pest — Onda Cowork Sells/Quotations — estrutura visual scoped.
 *
 * Cobertura estrutural via file_get_contents (Pest browser cobre interativo
 * quando estabilizar). Foca em garantir que:
 *  - CSS sells-cowork-quotations.css existe + scope correto + tokens locais
 *  - inertia.css importa sells-cowork-quotations.css
 *  - Quotations.tsx wrappa div outer com AMBAS classes (família + extensão)
 *  - Marcadores canônicos (US-SELL-QUOTATIONS-COWORK + ADR refs) presentes
 *  - Charter Quotations.charter.md preservado intacto
 *  - Componentes específicos da página (badge "Orçamento") renderizam
 *  - Anti-pattern: NÃO tocar Index.tsx (área proibida do agent vizinho)
 *
 * Refs:
 *  - resources/css/sells-cowork-quotations.css (novo)
 *  - resources/js/Pages/Sells/Quotations.tsx (modificado wrapper)
 *  - resources/js/Pages/Sells/Quotations.charter.md (preservado)
 *  - memory/requisitos/_DesignSystem/RUNBOOK-onda-cowork.md
 */

const QUOTATIONS_TSX_PATH = 'resources/js/Pages/Sells/Quotations.tsx';
const QUOTATIONS_CHARTER_PATH = 'resources/js/Pages/Sells/Quotations.charter.md';
const QUOTATIONS_CSS_PATH = 'resources/css/sells-cowork-quotations.css';
const QUOTATIONS_INERTIA_CSS_PATH = 'resources/css/inertia.css';
const QUOTATIONS_INDEX_TSX_PATH = 'resources/js/Pages/Sells/Index.tsx';
const QUOTATIONS_FAMILY_CSS_PATH = 'resources/css/sells-cowork.css';

function quotationsRead(string $rel): string
{
    return file_get_contents(base_path($rel));
}

// ─── Arquivos existem (smoke estrutural) ──────────────────────────────

it('CSS sells-cowork-quotations.css existe', function () {
    expect(file_exists(base_path(QUOTATIONS_CSS_PATH)))->toBeTrue();
});

it('Quotations.tsx ainda existe (não removido)', function () {
    expect(file_exists(base_path(QUOTATIONS_TSX_PATH)))->toBeTrue();
});

it('Quotations.charter.md preservado (não removido nem deslocado)', function () {
    expect(file_exists(base_path(QUOTATIONS_CHARTER_PATH)))->toBeTrue();
});

// ─── CSS scoped + tokens locais ──────────────────────────────────────

it('CSS sells-cowork-quotations.css scopa sob .sells-cowork-quotations', function () {
    $source = quotationsRead(QUOTATIONS_CSS_PATH);
    expect($source)->toContain('.sells-cowork-quotations {');
});

it('CSS define tokens locais âmbar (badge orçamento), convert (CTA), expiry (chip)', function () {
    $source = quotationsRead(QUOTATIONS_CSS_PATH);
    expect($source)
        ->toContain('--vd-quote-amber:')
        ->toContain('--vd-quote-amber-soft:')
        ->toContain('--vd-quote-convert:')
        ->toContain('--vd-quote-expired:')
        ->toContain('--vd-quote-expiring:');
});

it('CSS define classes principais (badge, convert-btn, expiry chip 3 variants)', function () {
    $source = quotationsRead(QUOTATIONS_CSS_PATH);
    expect($source)
        ->toContain('.sells-cowork-quotations .vd-quote-badge')
        ->toContain('.sells-cowork-quotations .vd-quote-convert-btn')
        ->toContain('.sells-cowork-quotations .vd-quote-expiry')
        ->toContain('.vd-quote-expiry.expired')
        ->toContain('.vd-quote-expiry.expiring')
        ->toContain('.vd-quote-expiry.fresh');
});

it('CSS NÃO redefine tokens da família Index (--bg, --accent, --text base preservados na família)', function () {
    $source = quotationsRead(QUOTATIONS_CSS_PATH);
    // Tokens locais OK (--vd-quote-*); mas a página NÃO redeclara variáveis canon
    // da família Index (--accent, --surface, --row-h, --shadow-pop etc.) — reusa via cascata.
    expect($source)
        ->not->toContain('--accent:')
        ->not->toContain('--surface:')
        ->not->toContain('--row-h:')
        ->not->toContain('--shadow-pop:')
        ->not->toContain('--sb-bg:');
});

// ─── inertia.css importa novo CSS ────────────────────────────────────

it('inertia.css importa sells-cowork-quotations.css', function () {
    $source = quotationsRead(QUOTATIONS_INERTIA_CSS_PATH);
    expect($source)->toContain('@import "./sells-cowork-quotations.css"');
});

it('inertia.css preserva imports da família Index (não removeu sells-cowork.css)', function () {
    $source = quotationsRead(QUOTATIONS_INERTIA_CSS_PATH);
    expect($source)
        ->toContain('@import "./sells-cowork.css"')
        ->toContain('@import "./sells-cowork-ia.css"')
        ->toContain('@import "./sells-cowork-curadoria.css"')
        ->toContain('@import "./sells-cowork-distribuicao.css"');
});

// ─── Quotations.tsx wrappa com escopo cumulativo ─────────────────────

it('Quotations.tsx wrappa div outer com AMBAS classes (sells-cowork + sells-cowork-quotations)', function () {
    $source = quotationsRead(QUOTATIONS_TSX_PATH);
    // Match em className só — descarta menções em comentário (ignora linhas que começam com //)
    $lines = explode("\n", $source);
    $hasWrapper = false;
    foreach ($lines as $line) {
        $trimmed = ltrim($line);
        if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) {
            continue;
        }
        // Procura className que contém AMBAS classes na mesma string literal
        if (preg_match('/className=["`][^"`]*\bsells-cowork\b[^"`]*\bsells-cowork-quotations\b[^"`]*["`]/', $line)) {
            $hasWrapper = true;
            break;
        }
    }
    expect($hasWrapper)->toBeTrue();
});

it('Quotations.tsx contém marcador US-SELL-QUOTATIONS-COWORK', function () {
    $source = quotationsRead(QUOTATIONS_TSX_PATH);
    expect($source)->toContain('US-SELL-QUOTATIONS-COWORK');
});

it('Quotations.tsx renderiza badge "Orçamento" (vd-quote-badge)', function () {
    $source = quotationsRead(QUOTATIONS_TSX_PATH);
    expect($source)
        ->toContain('vd-quote-badge')
        ->toContain('Orçamento');
});

it('Quotations.tsx preserva funcionalidade core (props, fetch, atalhos N/Esc)', function () {
    $source = quotationsRead(QUOTATIONS_TSX_PATH);
    expect($source)
        ->toContain('export default function SellsQuotations')
        ->toContain('fetchQuotations')
        ->toContain("if (e.key === 'n')")
        ->toContain("if (e.key === 'Escape')")
        ->toContain('SellsQuotations.layout');
});

it('Quotations.tsx preserva referência ADR canônicas (104, 149, 110, 143, 093)', function () {
    $source = quotationsRead(QUOTATIONS_TSX_PATH);
    expect($source)
        ->toContain('ADR 0104')
        ->toContain('ADR 0149')
        ->toContain('ADR 0110')
        ->toContain('ADR 0143')
        ->toContain('ADR 0093');
});

// ─── Charter preservado ──────────────────────────────────────────────

it('Quotations.charter.md preserva Mission + page header (frontmatter intacto)', function () {
    $source = quotationsRead(QUOTATIONS_CHARTER_PATH);
    expect($source)
        ->toContain('page: /sells/quotations')
        ->toContain('component: resources/js/Pages/Sells/Quotations.tsx')
        ->toContain('# Page Charter — /sells/quotations')
        ->toContain('## Mission');
});

// ─── Anti-pattern: áreas proibidas intactas ──────────────────────────

it('Index.tsx NÃO foi tocado por esta Onda (área proibida do agent vizinho)', function () {
    $source = quotationsRead(QUOTATIONS_INDEX_TSX_PATH);
    // Garante que Index NÃO recebeu marcador específico de Quotations (sinal de invasão)
    expect($source)
        ->not->toContain('US-SELL-QUOTATIONS-COWORK')
        ->not->toContain('sells-cowork-quotations');
});

it('sells-cowork.css canon família Index intacta (não recebeu tokens orçamento)', function () {
    $source = quotationsRead(QUOTATIONS_FAMILY_CSS_PATH);
    expect($source)
        ->not->toContain('--vd-quote-amber')
        ->not->toContain('--vd-quote-convert')
        ->not->toContain('vd-quote-badge');
});
