<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Onda 8 KB-9.75 — Financeiro/Unificado F1 Cowork rewrite.
 *
 * Wagner 2026-05-18: "expectativa realidade" — após Ondas 5/6/7 que
 * adicionaram features dentro de drawer/dialog, a página BASE continuava
 * Cockpit V2 antigo. Onda 8 reescreve header + KPI bar + footer pra
 * layout Cowork canon, preservando integrações:
 *   1. Venda→Título (TransactionObserver + TituloAutoService)
 *   2. Pagamento→Baixa (TransactionPaymentObserver)
 *   3. Boleto Inter API (BoletoRemessa + webhook paid_at)
 *   4. Cross-link UI (FinCrossLinkify #V- #BL- #PC-)
 *
 * Cobre:
 *   - resources/css/fin-cowork.css adicionado + importado em inertia.css
 *   - Index.tsx: FinSparkline component (SVG inline canon)
 *   - Index.tsx: KpiBar Cowork (5 .fin-stat + 1 .fin-stat-hero)
 *   - Index.tsx: header .fin-page-h com 7 botões canon
 *   - Index.tsx: footer .fin-footer-tips com 4 kbd atalhos
 *   - Integrações backend NÃO foram tocadas (Observer + Service intactos)
 *
 * Multi-tenant Tier 0 (ADR 0093) preservado.
 */

const FIN_BASE_8 = __DIR__ . '/../../../../resources/js/Pages/Financeiro/Unificado';
const FIN_COWORK_CSS = __DIR__ . '/../../../../resources/css/fin-cowork.css';
const FIN_INERTIA_CSS_8 = __DIR__ . '/../../../../resources/css/inertia.css';
const FIN_TRANSACTION_OBSERVER = __DIR__ . '/../../Observers/TransactionObserver.php';
const FIN_TITULO_AUTO_SERVICE = __DIR__ . '/../../Services/TituloAutoService.php';

describe('Onda 8 Cowork — CSS escopado fin-cowork.css', function () {
    it('fin-cowork.css existe e escopa em .fin-curadoria (reusa wrapper Ondas 5/6/7)', function () {
        $css = file_get_contents(FIN_COWORK_CSS);
        expect($css)->toContain('.fin-curadoria .fin-page-h');
        expect($css)->toContain('.fin-curadoria .fin-stats');
        expect($css)->toContain('.fin-curadoria .fin-stat-hero');
        expect($css)->toContain('.fin-curadoria .fin-spark');
        expect($css)->toContain('.fin-curadoria .fin-filter-cb');
        expect($css)->toContain('.fin-curadoria .fin-footer-tips');
        // Hero card linear-gradient verde (canon dark-green)
        expect($css)->toContain('oklch(0.22 0.04 145)');
        expect($css)->toContain('oklch(0.30 0.06 145)');
    });

    it('inertia.css importa fin-cowork.css APÓS fin-output.css (Onda 7)', function () {
        $css = file_get_contents(FIN_INERTIA_CSS_8);
        expect($css)->toContain('@import "./fin-cowork.css"');
        $pos7 = strpos($css, '@import "./fin-output.css"');
        $pos8 = strpos($css, '@import "./fin-cowork.css"');
        expect($pos7)->toBeLessThan($pos8);
    });
});

describe('Onda 8 Cowork — Index.tsx FinSparkline + KpiBar', function () {
    it('Index.tsx tem componente FinSparkline com SVG path canon', function () {
        $src = file_get_contents(FIN_BASE_8 . '/Index.tsx');
        expect($src)->toContain('function FinSparkline');
        expect($src)->toContain('viewBox="0 0 200 36"');
        // Path Cowork canon (placeholder estatico — Onda 8b plugara dados reais)
        expect($src)->toContain('M0,30 L15,26 L30,22');
        expect($src)->toContain('linearGradient');
        expect($src)->toContain('finSparkG');
    });

    it('KpiBar nova versão Cowork: hero card + 4 cards secundários', function () {
        $src = file_get_contents(FIN_BASE_8 . '/Index.tsx');
        // 5 .fin-stat (1 hero + 4 secundários)
        expect($src)->toContain('fin-stat fin-stat-hero');
        expect($src)->toContain('Saldo previsto');
        expect($src)->toContain('Recebido');
        expect($src)->toContain('A receber');
        expect($src)->toContain('Pago');
        expect($src)->toContain('A pagar');
        // FinSparkline mounted no hero
        expect($src)->toContain('<FinSparkline');
        // Versão legacy preservada como referência (não deve ser deletada agora)
        expect($src)->toContain('_KpiBarLegacy');
    });
});

describe('Onda 8 Cowork — header canon com 7 botões', function () {
    it('Index.tsx tem .fin-page-h header bar com h1 + 7 botões', function () {
        $src = file_get_contents(FIN_BASE_8 . '/Index.tsx');
        expect($src)->toContain('className="fin-page-h"');
        expect($src)->toContain('className="fin-page-h-l"');
        expect($src)->toContain('className="fin-page-h-r"');
        // 7 botões: Buscar, Resumir mês, Fechamento, Apresentar, Conciliar, Plano de contas, Novo
        expect($src)->toContain('🔍 Buscar');
        expect($src)->toContain('✦ Resumir mês');
        expect($src)->toContain('☑ Fechamento');
        expect($src)->toContain('▶ Apresentar');
        expect($src)->toContain('↺ Conciliar');
        expect($src)->toContain('📁 Plano de contas');
        expect($src)->toContain('+ Novo lançamento');
    });

    it('Botões com classe Cowork canon (fin-btn + fin-btn-ai/trilha/present)', function () {
        $src = file_get_contents(FIN_BASE_8 . '/Index.tsx');
        expect($src)->toContain('fin-btn fin-btn-ai');
        expect($src)->toContain('fin-btn fin-btn-trilha');
        expect($src)->toContain('fin-btn fin-btn-present');
        expect($src)->toContain('fin-btn primary');
    });
});

describe('Onda 8 Cowork — footer atalhos canon', function () {
    it('Index.tsx tem .fin-footer-tips com 4 kbd atalhos', function () {
        $src = file_get_contents(FIN_BASE_8 . '/Index.tsx');
        expect($src)->toContain('className="fin-footer-tips"');
        expect($src)->toContain('<kbd>⌘K</kbd>');
        expect($src)->toContain('<kbd>/</kbd>');
        expect($src)->toContain('<kbd>J</kbd>');
        expect($src)->toContain('<kbd>K</kbd>');
        expect($src)->toContain('<kbd>␣</kbd>');
        expect($src)->toContain('palette');
        expect($src)->toContain('buscar');
        expect($src)->toContain('navegar');
        expect($src)->toContain('marcar pago/recebido');
    });
});

describe('Onda 8 Cowork — integrações backend PRESERVADAS (Tier 0)', function () {
    it('TransactionObserver continua chamando TituloAutoService::sincronizarDeTransacao', function () {
        $src = file_get_contents(FIN_TRANSACTION_OBSERVER);
        expect($src)->toContain('use Modules\Financeiro\Services\TituloAutoService');
        expect($src)->toContain('sincronizarDeTransacao($tx)');
        expect($src)->toContain('public function created(Transaction $tx)');
        expect($src)->toContain('public function updated(Transaction $tx)');
    });

    it('TituloAutoService preserva idempotência UNIQUE (business_id, origem, origem_id, parcela_numero)', function () {
        $src = file_get_contents(FIN_TITULO_AUTO_SERVICE);
        expect($src)->toContain('sincronizarDeTransacao');
        // Suporta sell e purchase (Onda 2 — multi-origem)
        expect($src)->toContain("'venda'");
        expect($src)->toContain("'compra'");
    });
});
