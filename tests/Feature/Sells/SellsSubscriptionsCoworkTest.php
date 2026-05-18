<?php

declare(strict_types=1);

/**
 * Pest — Onda Cowork Sells/Subscriptions — estrutura visual scoped.
 *
 * Cobertura estrutural via file_get_contents (Pest browser cobre interativo
 * quando estabilizar). Foca em garantir que:
 *  - CSS sells-cowork-subscriptions.css existe + scope correto + tokens locais
 *  - inertia.css importa sells-cowork-subscriptions.css
 *  - Subscriptions.tsx wrappa div outer com AMBAS classes (família + extensão)
 *  - Marcadores canônicos (US-SELL-SUBSCRIPTIONS-COWORK + ADR refs) presentes
 *  - Charter Subscriptions.charter.md preservado intacto
 *  - Componentes específicos da página (badge "Assinatura", chip frequência,
 *    chip próxima fatura, status badge active/paused) renderizam
 *  - Anti-pattern: NÃO tocar Index.tsx, Quotations.tsx, Drafts.tsx, Edit.tsx,
 *    Show.tsx, Create.tsx (áreas proibidas dos agents vizinhos)
 *
 * Refs:
 *  - resources/css/sells-cowork-subscriptions.css (novo)
 *  - resources/js/Pages/Sells/Subscriptions.tsx (modificado wrapper + badges)
 *  - resources/js/Pages/Sells/Subscriptions.charter.md (preservado)
 *  - memory/requisitos/Sells/RUNBOOK-subscriptions.md
 *  - tests/Feature/Sells/SellsQuotationsCoworkTest.php (modelo a espelhar)
 */

const SUBSCRIPTIONS_TSX_PATH = 'resources/js/Pages/Sells/Subscriptions.tsx';
const SUBSCRIPTIONS_CHARTER_PATH = 'resources/js/Pages/Sells/Subscriptions.charter.md';
const SUBSCRIPTIONS_CSS_PATH = 'resources/css/sells-cowork-subscriptions.css';
const SUBSCRIPTIONS_INERTIA_CSS_PATH = 'resources/css/inertia.css';
const SUBSCRIPTIONS_INDEX_TSX_PATH = 'resources/js/Pages/Sells/Index.tsx';
const SUBSCRIPTIONS_QUOTATIONS_TSX_PATH = 'resources/js/Pages/Sells/Quotations.tsx';
const SUBSCRIPTIONS_FAMILY_CSS_PATH = 'resources/css/sells-cowork.css';

function subscriptionsRead(string $rel): string
{
    return file_get_contents(base_path($rel));
}

// ─── Arquivos existem (smoke estrutural) ──────────────────────────────

it('CSS sells-cowork-subscriptions.css existe', function () {
    expect(file_exists(base_path(SUBSCRIPTIONS_CSS_PATH)))->toBeTrue();
});

it('Subscriptions.tsx ainda existe (não removido)', function () {
    expect(file_exists(base_path(SUBSCRIPTIONS_TSX_PATH)))->toBeTrue();
});

it('Subscriptions.charter.md preservado (não removido nem deslocado)', function () {
    expect(file_exists(base_path(SUBSCRIPTIONS_CHARTER_PATH)))->toBeTrue();
});

// ─── CSS scoped + tokens locais ──────────────────────────────────────

it('CSS sells-cowork-subscriptions.css scopa sob .sells-cowork-subscriptions', function () {
    $source = subscriptionsRead(SUBSCRIPTIONS_CSS_PATH);
    expect($source)->toContain('.sells-cowork-subscriptions {');
});

it('CSS define tokens locais indigo (badge assinatura), active (emerald), paused (amber), freq (neutro)', function () {
    $source = subscriptionsRead(SUBSCRIPTIONS_CSS_PATH);
    expect($source)
        ->toContain('--vd-sub-indigo:')
        ->toContain('--vd-sub-indigo-soft:')
        ->toContain('--vd-sub-active:')
        ->toContain('--vd-sub-paused:')
        ->toContain('--vd-sub-freq-bg:');
});

it('CSS define classes principais (badge, freq chip, next chip, status badge active/paused)', function () {
    $source = subscriptionsRead(SUBSCRIPTIONS_CSS_PATH);
    expect($source)
        ->toContain('.sells-cowork-subscriptions .vd-sub-badge')
        ->toContain('.sells-cowork-subscriptions .vd-sub-freq')
        ->toContain('.sells-cowork-subscriptions .vd-sub-next')
        ->toContain('.sells-cowork-subscriptions .vd-sub-status')
        ->toContain('.vd-sub-status.active')
        ->toContain('.vd-sub-status.paused');
});

it('CSS NÃO redefine tokens da família Index (--bg, --accent, --text base preservados na família)', function () {
    $source = subscriptionsRead(SUBSCRIPTIONS_CSS_PATH);
    // Tokens locais OK (--vd-sub-*); mas a página NÃO redeclara variáveis canon
    // da família Index (--accent, --surface, --row-h, --shadow-pop etc.) — reusa via cascata.
    expect($source)
        ->not->toContain('--accent:')
        ->not->toContain('--surface:')
        ->not->toContain('--row-h:')
        ->not->toContain('--shadow-pop:')
        ->not->toContain('--sb-bg:');
});

// ─── inertia.css importa novo CSS ────────────────────────────────────

it('inertia.css importa sells-cowork-subscriptions.css', function () {
    $source = subscriptionsRead(SUBSCRIPTIONS_INERTIA_CSS_PATH);
    expect($source)->toContain('@import "./sells-cowork-subscriptions.css"');
});

it('inertia.css preserva imports da família Index (não removeu sells-cowork.css)', function () {
    $source = subscriptionsRead(SUBSCRIPTIONS_INERTIA_CSS_PATH);
    expect($source)
        ->toContain('@import "./sells-cowork.css"')
        ->toContain('@import "./sells-cowork-ia.css"')
        ->toContain('@import "./sells-cowork-curadoria.css"')
        ->toContain('@import "./sells-cowork-distribuicao.css"')
        ->toContain('@import "./sells-cowork-quotations.css"');
});

// ─── Subscriptions.tsx wrappa com escopo cumulativo ──────────────────

it('Subscriptions.tsx wrappa div outer com AMBAS classes (sells-cowork + sells-cowork-subscriptions)', function () {
    $source = subscriptionsRead(SUBSCRIPTIONS_TSX_PATH);
    // Match em className só — descarta menções em comentário (ignora linhas que começam com //)
    $lines = explode("\n", $source);
    $hasWrapper = false;
    foreach ($lines as $line) {
        $trimmed = ltrim($line);
        if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) {
            continue;
        }
        // Procura className que contém AMBAS classes na mesma string literal
        if (preg_match('/className=["`][^"`]*\bsells-cowork\b[^"`]*\bsells-cowork-subscriptions\b[^"`]*["`]/', $line)) {
            $hasWrapper = true;
            break;
        }
    }
    expect($hasWrapper)->toBeTrue();
});

it('Subscriptions.tsx contém marcador US-SELL-SUBSCRIPTIONS-COWORK', function () {
    $source = subscriptionsRead(SUBSCRIPTIONS_TSX_PATH);
    expect($source)->toContain('US-SELL-SUBSCRIPTIONS-COWORK');
});

it('Subscriptions.tsx renderiza badge "Assinatura" (vd-sub-badge)', function () {
    $source = subscriptionsRead(SUBSCRIPTIONS_TSX_PATH);
    expect($source)
        ->toContain('vd-sub-badge')
        ->toContain('Assinatura');
});

it('Subscriptions.tsx renderiza chips de frequência (vd-sub-freq) e próxima fatura (vd-sub-next)', function () {
    $source = subscriptionsRead(SUBSCRIPTIONS_TSX_PATH);
    expect($source)
        ->toContain('vd-sub-freq')
        ->toContain('vd-sub-next');
});

it('Subscriptions.tsx renderiza status badge ativa/pausada (vd-sub-status active/paused)', function () {
    $source = subscriptionsRead(SUBSCRIPTIONS_TSX_PATH);
    expect($source)
        ->toContain('vd-sub-status active')
        ->toContain('vd-sub-status paused');
});

it('Subscriptions.tsx preserva funcionalidade core (props, fetch, atalhos N/Esc, toggle)', function () {
    $source = subscriptionsRead(SUBSCRIPTIONS_TSX_PATH);
    expect($source)
        ->toContain('export default function SellsSubscriptions')
        ->toContain('fetchSubscriptions')
        ->toContain("if (e.key === 'n')")
        ->toContain("if (e.key === 'Escape')")
        ->toContain('toggleRecurring')
        ->toContain('SellsSubscriptions.layout');
});

it('Subscriptions.tsx preserva referência ADR canônicas (104, 149, 110, 093)', function () {
    $source = subscriptionsRead(SUBSCRIPTIONS_TSX_PATH);
    expect($source)
        ->toContain('ADR 0104')
        ->toContain('ADR 0149')
        ->toContain('ADR 0110')
        ->toContain('ADR 0093');
});

// ─── Charter preservado ──────────────────────────────────────────────

it('Subscriptions.charter.md preserva Mission + page header (frontmatter intacto)', function () {
    $source = subscriptionsRead(SUBSCRIPTIONS_CHARTER_PATH);
    expect($source)
        ->toContain('page: /sells/subscriptions')
        ->toContain('component: resources/js/Pages/Sells/Subscriptions.tsx')
        ->toContain('# Page Charter — /sells/subscriptions')
        ->toContain('## Mission');
});

// ─── Anti-pattern: áreas proibidas intactas ──────────────────────────

it('Index.tsx NÃO foi tocado por esta Onda (área proibida do agent vizinho)', function () {
    $source = subscriptionsRead(SUBSCRIPTIONS_INDEX_TSX_PATH);
    expect($source)
        ->not->toContain('US-SELL-SUBSCRIPTIONS-COWORK')
        ->not->toContain('sells-cowork-subscriptions');
});

it('Quotations.tsx NÃO foi tocado por esta Onda (área proibida do agent irmão)', function () {
    $source = subscriptionsRead(SUBSCRIPTIONS_QUOTATIONS_TSX_PATH);
    expect($source)
        ->not->toContain('US-SELL-SUBSCRIPTIONS-COWORK')
        ->not->toContain('sells-cowork-subscriptions');
});

it('sells-cowork.css canon família Index intacta (não recebeu tokens assinatura)', function () {
    $source = subscriptionsRead(SUBSCRIPTIONS_FAMILY_CSS_PATH);
    expect($source)
        ->not->toContain('--vd-sub-indigo')
        ->not->toContain('--vd-sub-active')
        ->not->toContain('vd-sub-badge');
});
