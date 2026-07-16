<?php

declare(strict_types=1);

/**
 * Contrato do FREEZER do par mock do KB — `/kb/v2` · `/sops` · `/kb/graph` · `/kb/graph/data`.
 *
 * `uses(Tests\TestCase::class)` já aplicado globalmente em tests/Pest.php
 * (uses(TestCase::class)->in(Modules/KB/Tests/Feature)). NÃO redeclarar aqui.
 *
 * =====================================================================================
 * O QUE MUDOU (2026-07-16) — de "contrato da rota viva" pra "contrato do freezer"
 * =====================================================================================
 * Até 2026-07-16 este arquivo tinha 6 testes (UC-KBV2-01..06) blindando a ROTA VIVA:
 * auth · render do componente · read-only · sem Jobs/IA · Tier 0 · fallback mock. Eles
 * eram honestos pro estado da época, mas o estado acabou: [W] mandou as 4 rotas pro
 * freezer porque serviam dado FICTÍCIO em prod com auth real (MOCK_NODES = KB de gráfica
 * num tenant de vestuário) e 4 ações mentiam sucesso sem persistir nada.
 *
 * Os 6 UCs não "falharam" — deixaram de ter objeto. Não há rota viva pra blindar. Mantê-los
 * verdes exigiria manter a rota, e mantê-los citados sem testar nada seria cobertura-fantasma
 * (o anti-padrão que o próprio casos-gate combate). Foram substituídos por UC-KBV2-11, que
 * prova o oposto: que as rotas NÃO respondem.
 *
 * Dois deles merecem lápide, porque a revisão adversarial mostrou que estavam podres:
 *   - UC-KBV2-05 (Tier 0 cross-tenant) passava POR CONSTRUÇÃO — sem prop `nodes`, biz=99
 *     nunca aparecia porque a tela não servia dado nenhum. Passaria mesmo sem multi-tenant.
 *   - UC-KBV2-06 assertava `missing('nodes')`, ou seja: o contrato PROIBIA a promoção — o
 *     Controller real deixaria um teste REQUIRED vermelho por sucesso.
 *
 * Este teste é a catraca do freezer: se alguém re-rotear sem decisão [W], ele fica VERMELHO
 * e obriga a conversa. Re-abrir é legítimo — mas é ato consciente, com este arquivo mudando
 * junto (e aí o contrato volta a ser o da tela real, com Controller, não o da closure mock).
 *
 * Casos: resources/js/Pages/kb/Index.v2.casos.md (UC-KBV2-11)
 *
 * @see Modules/KB/Http/routes.php — bloco FREEZER (porquê + caminho de volta + gatilho)
 * @see resources/js/Pages/kb/Index.v2.charter.md — charter preservado (status draft)
 */

// ── UC-KBV2-11 — as rotas do par mock NÃO respondem (freezer) ─────────────────
// Sem beforeEach de schema: o freezer não toca banco — só o roteador. Isso também
// tira o skip de SQLite que os 6 testes antigos carregavam (eles precisavam do schema
// kb_* e por isso só rodavam no MySQL do CT100; este roda em qualquer runner do CI).

it('UC-KBV2-11: as rotas nomeadas do par mock foram removidas', function () {
    // Nome de rota é o contrato que outro código consome (route('kb.v2') quebraria o
    // build). Se alguém re-rotear, este teste vermelho força a decisão consciente.
    expect(\Route::has('kb.v2'))->toBeFalse();
    expect(\Route::has('sops.index'))->toBeFalse();
    expect(\Route::has('kb.graph.page'))->toBeFalse();
    expect(\Route::has('kb.graph.data'))->toBeFalse();
});

it('UC-KBV2-11: os caminhos do par mock devolvem 404 (não servem mais ficção)', function () {
    // O ponto do freezer: quem digitar a URL NÃO vê mais SOP inventado. 404 é a
    // resposta honesta — a tela não existe pra fora. Anônimo de propósito: se a rota
    // existisse, o `auth` devolveria 302 e o teste pegaria a diferença (302 ≠ 404).
    foreach (['/kb/v2', '/sops', '/kb/graph', '/kb/graph/data'] as $path) {
        expect($this->get($path)->status())->toBe(404, "{$path} ainda responde — o freezer vazou");
    }
});

it('UC-KBV2-11: a V3 (/kb, dado REAL) segue viva — o freezer não a atingiu', function () {
    // Controle-negativo do freezer: prova que removi o par mock e NÃO o KB inteiro.
    // A V3 serve os docs canônicos reais (McpMemoryDocument) e é pra onde o sidebar
    // aponta. Se este teste ficar vermelho, o freezer passou do ponto.
    expect(\Route::has('kb.index'))->toBeTrue();
    expect($this->get('/kb')->status())->not->toBe(404);
});
