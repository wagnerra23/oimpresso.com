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
