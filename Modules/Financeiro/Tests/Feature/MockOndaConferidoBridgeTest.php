<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Mock Onda 5 — Bridge Conferido (Integração #3) — Wagner 2026-05-18
 *
 * Bridge JS espelha o toggle "Conferido" do mock Cowork canon
 * (FinConferidoToggle em financeiro-curation.jsx) em POST/DELETE Laravel real
 *   POST   /financeiro/unificado/{id}/conferir   → marca conferido_by/at
 *   DELETE /financeiro/unificado/{id}/conferir   → limpa conferido_by/at
 *
 * Cobre asserts ESTRUTURAIS do arquivo bridge (sem boot da app — Tier 0
 * safe, smoke estático):
 *   - Arquivo existe em public/cowork-preview/_oimpresso-bridge-conferido.js
 *   - Event listener click registrado no document (event delegation)
 *   - Match seletor .fin-conferido-toggle (CSS class do toggle no JSX)
 *   - Faz fetch pra /financeiro/unificado/{id}/conferir
 *   - Inclui CSRF token via meta[name="csrf-token"]
 *   - Console log diagnóstico canon "Conferido toggle synced"
 *   - Extrai id Laravel via regex /^[RP]-(\d+)$/ (CoworkDataMapper format)
 *   - Não toca financeiro-curation.jsx (restrição Tier 0 Wagner)
 *
 * Não toca trait RendersMockCowork.php (Wagner consolida bridge scripts
 * num único PR depois).
 */

const FIN_BRIDGE_CONFERIDO = __DIR__ . '/../../../../public/cowork-preview/_oimpresso-bridge-conferido.js';
const FIN_CURATION_JSX = __DIR__ . '/../../../../public/cowork-preview/financeiro-curation.jsx';

describe('Mock Onda 5 — Bridge Conferido (estrutural)', function () {
    it('arquivo _oimpresso-bridge-conferido.js existe em public/cowork-preview/', function () {
        expect(file_exists(FIN_BRIDGE_CONFERIDO))->toBeTrue();
    });

    it('registra event listener click no document (event delegation, capture)', function () {
        $src = file_get_contents(FIN_BRIDGE_CONFERIDO);
        expect($src)->toContain("document.addEventListener('click'");
        // capture: true garante interceptar antes de React rerenderizar
        expect($src)->toContain('true');
    });

    it('filtra alvos via .fin-conferido-toggle (CSS class do toggle no JSX)', function () {
        $src = file_get_contents(FIN_BRIDGE_CONFERIDO);
        expect($src)->toContain('.fin-conferido-toggle');
        // closest() é o padrão event-delegation canônico
        expect($src)->toContain('.closest(');
    });

    it('faz fetch pra /financeiro/unificado/{id}/conferir', function () {
        $src = file_get_contents(FIN_BRIDGE_CONFERIDO);
        expect($src)->toContain('/financeiro/unificado/');
        expect($src)->toContain('/conferir');
        expect($src)->toContain('fetch(');
    });

    it('usa POST pra marcar e DELETE pra desmarcar', function () {
        $src = file_get_contents(FIN_BRIDGE_CONFERIDO);
        expect($src)->toContain("'POST'");
        expect($src)->toContain("'DELETE'");
        expect($src)->toContain('marcar');
        expect($src)->toContain('desmarcar');
    });

    it('inclui CSRF token via meta[name="csrf-token"]', function () {
        $src = file_get_contents(FIN_BRIDGE_CONFERIDO);
        expect($src)->toContain("meta[name=\"csrf-token\"]");
        expect($src)->toContain('X-CSRF-TOKEN');
    });

    it('usa credentials: same-origin (Tier 0 cookie session)', function () {
        $src = file_get_contents(FIN_BRIDGE_CONFERIDO);
        expect($src)->toContain("credentials: 'same-origin'");
    });

    it('console log diagnóstico canon "Conferido toggle synced"', function () {
        $src = file_get_contents(FIN_BRIDGE_CONFERIDO);
        expect($src)->toContain('Conferido toggle synced');
        expect($src)->toContain('action=%s');
    });

    it('extrai id Laravel via regex /^[RP]-(\d+)$/ (CoworkDataMapper format)', function () {
        $src = file_get_contents(FIN_BRIDGE_CONFERIDO);
        // Regex canônico — extractLaravelId
        expect($src)->toContain('/^[RP]-(\d+)$/');
        expect($src)->toContain('extractLaravelId');
    });

    it('graceful skip pra id não-numérico (mock template "R-2641a")', function () {
        $src = file_get_contents(FIN_BRIDGE_CONFERIDO);
        // Loga e retorna sem fetch quando id não bate regex
        expect($src)->toContain('SKIP');
        expect($src)->toContain('mock template');
    });

    it('NÃO modifica financeiro-curation.jsx (restrição Tier 0 Wagner Onda 5)', function () {
        // Garantia anti-regressão: useFinConferido continua usando localStorage
        // como fonte primária. Bridge é overlay de persistência, não substitui.
        $src = file_get_contents(FIN_CURATION_JSX);
        expect($src)->toContain('oimpresso.financeiro.conferido');
        expect($src)->toContain('function useFinConferido()');
        // Nenhuma referência ao endpoint Laravel dentro do JSX
        expect($src)->not->toContain('/unificado/');
        expect($src)->not->toContain('fetch(');
    });
});

describe('Mock Onda 5 — Endpoints Laravel já existem (sanity)', function () {
    it('UnificadoController tem método conferir() + unconferir()', function () {
        $src = file_get_contents(__DIR__ . '/../../Http/Controllers/UnificadoController.php');
        expect($src)->toContain('public function conferir(');
        expect($src)->toContain('public function unconferir(');
        // Tier 0: session('user.business_id') filtra por tenant
        expect($src)->toContain("session('user.business_id')");
    });

    it('routes/web.php registra POST/DELETE /unificado/{id}/conferir', function () {
        $src = file_get_contents(__DIR__ . '/../../routes/web.php');
        expect($src)->toContain("Route::post('/unificado/{id}/conferir'");
        expect($src)->toContain("Route::delete('/unificado/{id}/conferir'");
        expect($src)->toContain("unificado.conferir");
        expect($src)->toContain("unificado.unconferir");
    });
});
