<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Mock Onda 6 — Bridge Edit (Integração #6) — Wagner 2026-05-18
 *
 * Bridge JS espelha o "✓ Salvar" do FinEditPanel (mock Cowork canon
 * financeiro-curation.jsx) em PUT Laravel real
 *   PUT /financeiro/unificado/{id}   → atualiza Titulo via UpdateTituloRequest
 *
 * Cobre asserts ESTRUTURAIS do arquivo bridge (sem boot da app — Tier 0
 * safe, smoke estático):
 *   - Arquivo existe em public/cowork-preview/_oimpresso-bridge-edit.js
 *   - Event listener registrado pra 'oimpresso:fin-edit' (CustomEvent)
 *   - Faz fetch PUT /financeiro/unificado/{id}
 *   - Inclui CSRF token via meta[name="csrf-token"]
 *   - Console log diagnóstico canon "Edit bridge synced"
 *   - Extrai id Laravel via regex /^[RP]-(\d+)$/ (CoworkDataMapper format)
 *   - Graceful skip pra id não-numérico (mock template "R-2641a")
 *   - Vencimento required guard (evita 422 ruidoso)
 *   - Categoria nome→id NÃO enviado (gotcha Onda 7)
 *
 * JSX modificado em MÍNIMO (1 dispatch event canon no botão Salvar do
 * FinEditPanel — financeiro-curation.jsx). Restante (panel inputs,
 * useFinEdits localStorage, applied()) preservado intacto.
 *
 * Não toca trait RendersMockCowork.php (Wagner consolida bridge scripts
 * num único PR depois).
 */

const FIN_BRIDGE_EDIT = __DIR__ . '/../../../../public/cowork-preview/_oimpresso-bridge-edit.js';
const FIN_CURATION_JSX_ONDA6 = __DIR__ . '/../../../../public/cowork-preview/financeiro-curation.jsx';

describe('Mock Onda 6 — Bridge Edit (estrutural)', function () {
    it('arquivo _oimpresso-bridge-edit.js existe em public/cowork-preview/', function () {
        expect(file_exists(FIN_BRIDGE_EDIT))->toBeTrue();
    });

    it('registra event listener pra oimpresso:fin-edit (CustomEvent)', function () {
        $src = file_get_contents(FIN_BRIDGE_EDIT);
        expect($src)->toContain("window.addEventListener('oimpresso:fin-edit'");
    });

    it('faz fetch PUT pra /financeiro/unificado/{id}', function () {
        $src = file_get_contents(FIN_BRIDGE_EDIT);
        expect($src)->toContain('/financeiro/unificado/');
        expect($src)->toContain("method: 'PUT'");
        expect($src)->toContain('fetch(');
    });

    it('inclui CSRF token via meta[name="csrf-token"]', function () {
        $src = file_get_contents(FIN_BRIDGE_EDIT);
        expect($src)->toContain("meta[name=\"csrf-token\"]");
        expect($src)->toContain('X-CSRF-TOKEN');
    });

    it('usa credentials: same-origin (Tier 0 cookie session)', function () {
        $src = file_get_contents(FIN_BRIDGE_EDIT);
        expect($src)->toContain("credentials: 'same-origin'");
    });

    it('console log diagnóstico canon "Edit bridge synced"', function () {
        $src = file_get_contents(FIN_BRIDGE_EDIT);
        expect($src)->toContain('Edit bridge synced');
    });

    it('extrai id Laravel via regex /^[RP]-(\d+)$/ (CoworkDataMapper format)', function () {
        $src = file_get_contents(FIN_BRIDGE_EDIT);
        expect($src)->toContain('/^[RP]-(\d+)$/');
        expect($src)->toContain('extractLaravelId');
    });

    it('graceful skip pra id não-numérico (mock template "R-2641a")', function () {
        $src = file_get_contents(FIN_BRIDGE_EDIT);
        expect($src)->toContain('SKIP');
        expect($src)->toContain('mock template');
    });

    it('mapeia party → cliente_descricao e dueISO → vencimento (UpdateTituloRequest)', function () {
        $src = file_get_contents(FIN_BRIDGE_EDIT);
        expect($src)->toContain('cliente_descricao');
        expect($src)->toContain('vencimento');
        // amount → valor_total (preserva guard imutabilidade)
        expect($src)->toContain('valor_total');
    });

    it('NÃO envia categoria nome→id (gotcha Onda 7 — backend exige integer ID)', function () {
        $src = file_get_contents(FIN_BRIDGE_EDIT);
        // Bridge documenta a omissão explicitamente (não há assignment categoria_id)
        expect($src)->not->toMatch('/payload\.categoria_id\s*=/');
    });

    it('vencimento required guard (aborta antes do fetch se ausente)', function () {
        $src = file_get_contents(FIN_BRIDGE_EDIT);
        // Guarda anti-422 ruidoso pra payload sem vencimento
        expect($src)->toContain('vencimento ausente');
    });

    it('FinEditPanel dispatch CustomEvent oimpresso:fin-edit no botão Salvar', function () {
        // Modificação mínima JSX (1 linha try/catch) em financeiro-curation.jsx
        $src = file_get_contents(FIN_CURATION_JSX_ONDA6);
        expect($src)->toContain("'oimpresso:fin-edit'");
        expect($src)->toContain('Integração #6 oimpresso 2026-05-18');
        // Try/catch silencioso preserva comportamento mock se bridge ausente
        expect($src)->toContain('try {');
    });

    it('NÃO faz fetch direto no JSX (bridge é overlay, não substitui localStorage)', function () {
        $src = file_get_contents(FIN_CURATION_JSX_ONDA6);
        // useFinEdits continua escrevendo no localStorage como fonte primária
        expect($src)->toContain('oimpresso.financeiro.edits');
        expect($src)->toContain('function useFinEdits()');
        // Nenhuma chamada fetch dentro do JSX (overlay puro via event)
        expect($src)->not->toContain('fetch(');
    });
});

describe('Mock Onda 6 — Endpoint Laravel já existe (sanity)', function () {
    it('UnificadoController tem método update()', function () {
        $src = file_get_contents(__DIR__ . '/../../Http/Controllers/UnificadoController.php');
        expect($src)->toContain('public function update(');
        // Tier 0: session('user.business_id') filtra por tenant
        expect($src)->toContain("session('user.business_id')");
        // findOrFail garante isolamento por business
        expect($src)->toContain('findOrFail');
    });

    it('routes/web.php registra PUT /unificado/{id}', function () {
        $src = file_get_contents(__DIR__ . '/../../routes/web.php');
        expect($src)->toContain("Route::put('/unificado/{id}'");
        expect($src)->toContain("unificado.update");
    });

    it('UpdateTituloRequest valida cliente_descricao, observacoes, vencimento, valor_total', function () {
        $src = file_get_contents(__DIR__ . '/../../Http/Requests/UpdateTituloRequest.php');
        expect($src)->toContain('cliente_descricao');
        expect($src)->toContain('observacoes');
        expect($src)->toContain('vencimento');
        expect($src)->toContain('valor_total');
        // Guard de imutabilidade pós-baixa
        expect($src)->toContain('assertValorMutavel');
    });
});
