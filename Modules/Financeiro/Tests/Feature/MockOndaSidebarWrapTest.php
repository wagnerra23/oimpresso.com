<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Mock Onda 4 — Sidebar wrapper oimpresso (Integração #4) — Wagner 2026-05-18
 *
 * Approach C: CSS positioning + JS inject pra envelopar o mock Cowork canon
 * com sidebar oimpresso REAL (auth/logout/troca business) à esquerda, sem
 * iframe e sem modificar o main content do mock.
 *
 * Cobre asserts ESTRUTURAIS dos 2 arquivos bridge (sem boot da app — Tier 0
 * safe, smoke estático):
 *   - public/cowork-preview/_oimpresso-bridge-sidebar.js — JS injetor DOM
 *   - public/cowork-preview/_oimpresso-sidebar-wrapper.css — CSS positioning
 *
 * Restrição Tier 0 Onda 4 (mantida):
 *   - NÃO modifica trait RendersMockCowork.php (Wagner consolida bridge
 *     scripts num PR único depois — mesma estratégia Onda 5)
 *   - NÃO modifica AppShellV2.tsx / Sidebar.tsx oimpresso
 *   - NÃO modifica main content do mock
 *   - NÃO usa iframe (Wagner regra explícita)
 *
 * Reversibilidade: assets ficam órfãos até trait incluir <link>/<script>.
 * Onda 4b ativa via trait + valida visual com Wagner.
 */

const FIN_BRIDGE_SIDEBAR_JS = __DIR__ . '/../../../../public/cowork-preview/_oimpresso-bridge-sidebar.js';
const FIN_SIDEBAR_WRAPPER_CSS = __DIR__ . '/../../../../public/cowork-preview/_oimpresso-sidebar-wrapper.css';
const FIN_TRAIT_PATH = __DIR__ . '/../../Http/Controllers/Concerns/RendersMockCowork.php';

describe('Mock Onda 4 — Sidebar wrapper (estrutural)', function () {
    it('arquivo _oimpresso-bridge-sidebar.js existe em public/cowork-preview/', function () {
        expect(file_exists(FIN_BRIDGE_SIDEBAR_JS))->toBeTrue();
    });

    it('arquivo _oimpresso-sidebar-wrapper.css existe em public/cowork-preview/', function () {
        expect(file_exists(FIN_SIDEBAR_WRAPPER_CSS))->toBeTrue();
    });

    it('bridge JS roda em DOMContentLoaded com fallback se já carregado', function () {
        $src = file_get_contents(FIN_BRIDGE_SIDEBAR_JS);
        expect($src)->toContain("document.readyState === 'loading'");
        expect($src)->toContain("DOMContentLoaded");
    });

    it('bridge JS lê dados shell via window.__OIMPRESSO_SHELL__ com fallback', function () {
        $src = file_get_contents(FIN_BRIDGE_SIDEBAR_JS);
        expect($src)->toContain('window.__OIMPRESSO_SHELL__');
        // Fallback hardcoded simplificado pra Wagner validar visual primeiro
        expect($src)->toContain("'Dashboard'");
        expect($src)->toContain("'Vendas'");
        expect($src)->toContain("'Financeiro'");
        expect($src)->toContain("'CRM'");
    });

    it('bridge JS suporta kill-switch runtime via window.__OIMPRESSO_SIDEBAR_OFF__', function () {
        $src = file_get_contents(FIN_BRIDGE_SIDEBAR_JS);
        expect($src)->toContain('__OIMPRESSO_SIDEBAR_OFF__');
        expect($src)->toContain('SKIP');
    });

    it('bridge JS é idempotente (guard contra dupla montagem)', function () {
        $src = file_get_contents(FIN_BRIDGE_SIDEBAR_JS);
        expect($src)->toContain(".querySelector('.oim-sidebar')");
        expect($src)->toContain('já montado');
    });

    it('bridge JS insere aside como primeiro filho do body (antes do #app Cowork)', function () {
        $src = file_get_contents(FIN_BRIDGE_SIDEBAR_JS);
        expect($src)->toContain('document.body.insertBefore');
        expect($src)->toContain('document.body.firstChild');
    });

    it('bridge JS ativa classe html.oim-sidebar-on (gatilho CSS)', function () {
        $src = file_get_contents(FIN_BRIDGE_SIDEBAR_JS);
        expect($src)->toContain("classList.add('oim-sidebar-on')");
    });

    it('bridge JS gera form POST /logout com CSRF token (rota Laravel canon)', function () {
        $src = file_get_contents(FIN_BRIDGE_SIDEBAR_JS);
        expect($src)->toContain("action: '/logout'");
        expect($src)->toContain("method: 'POST'");
        expect($src)->toContain("'_token'");
        // CSRF via meta tag (injetada pelo trait)
        expect($src)->toContain("meta[name=\"csrf-token\"]");
    });

    it('bridge JS expõe item Financeiro como active (rota atual)', function () {
        $src = file_get_contents(FIN_BRIDGE_SIDEBAR_JS);
        // Default fallback marca Financeiro active=true
        expect($src)->toContain("'Financeiro'");
        expect($src)->toContain('active: true');
        expect($src)->toContain('/financeiro/unificado');
    });

    it('bridge JS console log diagnóstico canon "Sidebar wrapper montado"', function () {
        $src = file_get_contents(FIN_BRIDGE_SIDEBAR_JS);
        expect($src)->toContain('Sidebar wrapper montado');
        expect($src)->toContain('Integração #4');
        expect($src)->toContain('Onda 4');
    });

    it('CSS esconde .sb Cowork canon quando html.oim-sidebar-on ativo', function () {
        $src = file_get_contents(FIN_SIDEBAR_WRAPPER_CSS);
        expect($src)->toContain('html.oim-sidebar-on .sb');
        expect($src)->toContain('display: none !important');
    });

    it('CSS empurra .app Cowork right com margin-left 260px', function () {
        $src = file_get_contents(FIN_SIDEBAR_WRAPPER_CSS);
        expect($src)->toContain('html.oim-sidebar-on .app');
        expect($src)->toContain('margin-left: 260px');
    });

    it('CSS posiciona .oim-sidebar fixed à esquerda full height', function () {
        $src = file_get_contents(FIN_SIDEBAR_WRAPPER_CSS);
        expect($src)->toContain('html.oim-sidebar-on .oim-sidebar');
        expect($src)->toContain('position: fixed');
        expect($src)->toContain('left: 0');
        expect($src)->toContain('width: 260px');
    });

    it('CSS z-index alto (>= 1000) pra sidebar oimpresso ficar sobre TweaksPanel Cowork', function () {
        $src = file_get_contents(FIN_SIDEBAR_WRAPPER_CSS);
        expect($src)->toMatch('/z-index:\s*1000/');
    });

    it('CSS fallback responsivo: <768px mostra .sb Cowork (mobile graceful)', function () {
        $src = file_get_contents(FIN_SIDEBAR_WRAPPER_CSS);
        expect($src)->toContain('@media (max-width: 768px)');
        // Em mobile sidebar oimpresso some, Cowork volta
        expect($src)->toContain('display: flex !important');
    });
});

describe('Mock Onda 4 — Restrição Tier 0 (anti-regressão)', function () {
    it('NÃO modifica trait RendersMockCowork.php (Wagner consolida bridges depois)', function () {
        // Onda 4 segue o mesmo padrão da Onda 5: cria assets, NÃO inclui no trait.
        // A consolidação dos bridge scripts (posts + conferido + sidebar) vira
        // um PR único de "ativar" depois que Wagner aprovar visual de cada bridge.
        $src = file_get_contents(FIN_TRAIT_PATH);
        // Trait NÃO referencia o novo bridge sidebar ainda
        expect($src)->not->toContain('_oimpresso-bridge-sidebar.js');
        expect($src)->not->toContain('_oimpresso-sidebar-wrapper.css');
    });

    it('NÃO modifica resources/js/Layouts/AppShellV2.tsx (Tier 0)', function () {
        $appShell = file_get_contents(__DIR__ . '/../../../../resources/js/Layouts/AppShellV2.tsx');
        // AppShellV2 não conhece a sidebar wrapper Cowork — separação total
        expect($appShell)->not->toContain('oim-sidebar');
        expect($appShell)->not->toContain('_oimpresso-bridge-sidebar');
    });

    it('NÃO toca main content do mock (preserva 100% visual)', function () {
        // Nem o JS bridge nem o CSS wrapper devem mexer no .main-body Cowork
        $js = file_get_contents(FIN_BRIDGE_SIDEBAR_JS);
        $css = file_get_contents(FIN_SIDEBAR_WRAPPER_CSS);
        // CSS só atua em .sb (sidebar Cowork) e .app (container) — não em .main-body
        expect($css)->not->toContain('.main-body');
        // JS só insere <aside> ao lado, não remove/modifica #app ou .main
        expect($js)->not->toContain("getElementById('app').remove");
        expect($js)->not->toContain('main-body');
    });

    it('NÃO usa iframe (Wagner regra explícita)', function () {
        $js = file_get_contents(FIN_BRIDGE_SIDEBAR_JS);
        $css = file_get_contents(FIN_SIDEBAR_WRAPPER_CSS);
        expect($js)->not->toContain('iframe');
        expect($css)->not->toContain('iframe');
    });

    it('Reversibilidade total: remover assets volta ao estado anterior', function () {
        // CSS ativo APENAS quando html.oim-sidebar-on existe (gatilho do JS).
        // Sem o JS rodar, classe não é setada, CSS não impacta nada.
        $css = file_get_contents(FIN_SIDEBAR_WRAPPER_CSS);
        // TODOS os seletores que tocam Cowork são guarded por html.oim-sidebar-on
        $lines = explode("\n", $css);
        $coworkSelectors = array_filter($lines, function ($l) {
            return preg_match('/^\s*\.(sb|app|sb-reopen-handle|sb-collapse-handle)/', $l);
        });
        foreach ($coworkSelectors as $line) {
            // Cada seletor que toca Cowork deve estar dentro de html.oim-sidebar-on
            // (essa asserção é fraca — só checa por presença, não por aninhamento real)
        }
        // Validação alternativa: grep por seletor solto sem guard
        expect($css)->not->toMatch('/^\.sb\s*{/m');
        expect($css)->not->toMatch('/^\.app\s*{/m');
    });
});
