<?php

declare(strict_types=1);

/**
 * KbAnswerServiceTest — cobre os métodos determinísticos do KbAnswerService
 * extraído de KbAnswerTool (2026-07-01, PR 1 do RAGAS real-eval).
 *
 * Escopo: formatação pura (sem DB, sem LLM) — extrairExcerpt / renderFontes /
 * fallbackSemIa + resolução via container. Garante que a refatoração que moveu
 * a lógica da Tool pro service PRESERVOU o comportamento byte-a-byte.
 *
 * O retrieval (FASE 1, DB) e a síntese (FASE 2, LLM) são cobertos pelo RAGAS
 * real-eval rodando no CT 100 staging (infra real) — aqui só o que é determinístico.
 *
 * @group jana
 *
 * @see Modules/Jana/Services/Kb/KbAnswerService.php
 * @see Modules/Jana/Mcp/Tools/KbAnswerTool.php
 */

use Illuminate\Support\Collection;
use Modules\Jana\Services\Kb\KbAnswerService;

// Tests\TestCase já é aplicado globalmente em tests/Pest.php. NÃO redeclarar.

/** Fixture leve de "doc" (props acessadas por renderFontes/fallbackSemIa). */
function fakeKbDoc(array $attrs): object
{
    return (object) array_merge([
        'slug' => 'doc-x',
        'title' => 'Título X',
        'type' => 'adr',
        'module' => 'core',
        'content_md' => 'conteúdo',
        'git_path' => null,
    ], $attrs);
}

it('resolve o service via container', function () {
    expect(app(KbAnswerService::class))->toBeInstanceOf(KbAnswerService::class);
});

it('extrai excerpt pulando frontmatter YAML', function () {
    $svc = app(KbAnswerService::class);

    $body = "---\ntitle: Foo\nstatus: accepted\n---\nEste é o corpo real do doc.";
    expect($svc->extrairExcerpt($body, 400))->toBe('Este é o corpo real do doc.');
});

it('trunca excerpt com reticências quando excede maxLen', function () {
    $svc = app(KbAnswerService::class);

    $body = str_repeat('a', 500);
    $out = $svc->extrairExcerpt($body, 400);

    expect(mb_strlen($out))->toBe(403); // 400 + '...'
    expect($out)->toEndWith('...');
});

it('renderiza bloco FONTES numerado com slug, tipo, módulo e path', function () {
    $svc = app(KbAnswerService::class);

    $docs = new Collection([
        fakeKbDoc(['slug' => '0093-multi-tenant', 'title' => 'Multi-tenant Tier 0', 'type' => 'adr', 'module' => 'core', 'content_md' => 'business_id global scope.', 'git_path' => 'memory/decisions/0093.md']),
        fakeKbDoc(['slug' => 'sess-01', 'title' => 'Sessão 1', 'type' => 'session', 'module' => 'Jana', 'content_md' => 'log da sessão.', 'git_path' => null]),
    ]);

    $out = $svc->renderFontes($docs);

    expect($out)->toContain('### Fonte #1 — `0093-multi-tenant`');
    expect($out)->toContain('**Multi-tenant Tier 0** _(tipo: adr · módulo: core)_');
    expect($out)->toContain('Path: `memory/decisions/0093.md`');
    // git_path null → fallback memory/{type}s/{slug}.md
    expect($out)->toContain('### Fonte #2 — `sess-01`');
    expect($out)->toContain('Path: `memory/sessions/sess-01.md`');
    expect($out)->toContain("\n\n---\n\n"); // separador entre fontes
});

it('fallbackSemIa devolve markdown honesto com citações limitadas', function () {
    $svc = app(KbAnswerService::class);

    $docs = new Collection([
        fakeKbDoc(['slug' => 'a', 'content_md' => "linha 1\n\nlinha 2", 'git_path' => 'memory/decisions/a.md']),
        fakeKbDoc(['slug' => 'b', 'content_md' => 'texto b', 'git_path' => 'memory/decisions/b.md']),
        fakeKbDoc(['slug' => 'c', 'content_md' => 'texto c', 'git_path' => 'memory/decisions/c.md']),
    ]);

    $out = $svc->fallbackSemIa('minha pergunta', $docs, 2);

    expect($out)->toStartWith('Resposta:');
    expect($out)->toContain('Citações:');
    expect($out)->toContain('- [a](memory/decisions/a.md)');
    expect($out)->toContain('- [b](memory/decisions/b.md)');
    expect($out)->not->toContain('[c]'); // limitado a maxCitacoes=2
    expect($out)->toEndWith('Confiança: baixa');
});

/*
|--------------------------------------------------------------------------
| extrairJanela — a janela CONTEM o match (2026-09-04)
|--------------------------------------------------------------------------
| Medido no CT 100 contra o gold-set: o excerpt de 400 chars pegava o CABECALHO
| do doc, e o juiz RAGAS via 0,3311 do ground_truth quando o doc certo ja estava
| recuperado (teto 0,9805). A janela centrada no match leva isso a 0,7110.
|
| O teste que MORDE e o de alinhamento: o fold de acentos precisa preservar a
| CONTAGEM DE CARACTERES, senao o offset do match desloca e a janela sai cortada
| no lugar errado. Vem com controle negativo — o head no mesmo orcamento NAO
| acha o marcador, entao o teste falha de verdade se alguem voltar pro head.
*/

it('extrairJanela devolve a janela que contém o match, mesmo com acentos antes dele', function () {
    $svc = app(KbAnswerService::class);

    // ~1200 chars acentuados ANTES do marcador — tem que passar do orçamento (600),
    // senão o head TAMBÉM acha e o controle negativo abaixo não prova nada.
    // É aqui que um fold que muda o comprimento desalinha o corte.
    $prefixo = str_repeat('ções áàâã ', 120);
    $body = $prefixo.' MARCADOR_UNICO_XYZ '.str_repeat('z', 3000);

    $janela = $svc->extrairJanela($body, 'marcador unico xyz', 600);

    expect($janela)->toContain('MARCADOR_UNICO_XYZ');

    // CONTROLE NEGATIVO: o comportamento ANTIGO (head no mesmo orçamento) não acha.
    // Sem esta linha o teste passaria mesmo se extrairJanela virasse um head.
    expect($svc->extrairExcerpt($body, 600))->not->toContain('MARCADOR_UNICO_XYZ');
});

it('extrairJanela cai no head quando nenhum termo da pergunta casa', function () {
    $svc = app(KbAnswerService::class);

    $body = str_repeat('conteudo irrelevante ', 200);
    $janela = $svc->extrairJanela($body, 'assunto completamente ausente disto', 300);

    // Piso: nunca pior que o comportamento antigo.
    expect($janela)->toBe($svc->extrairExcerpt($body, 300));
});

it('extrairJanela cai no head quando a pergunta só tem stopword/termo curto', function () {
    $svc = app(KbAnswerService::class);

    $body = str_repeat('texto qualquer ', 200);

    // 'como', 'isso', 'e' → stopwords/curtos: sobra zero termo útil.
    expect($svc->extrairJanela($body, 'como e isso', 300))
        ->toBe($svc->extrairExcerpt($body, 300));
});

it('extrairJanela devolve o corpo inteiro quando cabe no orçamento', function () {
    $svc = app(KbAnswerService::class);

    $body = "---\ntitle: Foo\n---\nCorpo curto sobre multi-tenant.";

    expect($svc->extrairJanela($body, 'multi-tenant', 400))
        ->toBe('Corpo curto sobre multi-tenant.'); // frontmatter fora, sem reticências
});

it('renderFontes sem pergunta preserva o comportamento antigo (head)', function () {
    $svc = app(KbAnswerService::class);

    // 'ALVOZINHO' com 9 chars: termos <5 chars são descartados de propósito
    // (stopword-like), então um alvo curto cairia no head e o teste mentiria.
    $body = str_repeat('a', 300).'ALVOZINHO'.str_repeat('b', 3000);
    $docs = new Collection([fakeKbDoc(['slug' => 'd', 'content_md' => $body])]);

    // Sem pergunta → head puro: para em 200, muito antes do alvo em 300.
    expect($svc->renderFontes($docs, '', 200))->not->toContain('ALVOZINHO');

    // Com pergunta → janela centrada acha.
    expect($svc->renderFontes($docs, 'alvozinho', 200))->toContain('ALVOZINHO');
});
