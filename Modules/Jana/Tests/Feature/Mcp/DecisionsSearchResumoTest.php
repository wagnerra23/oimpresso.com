<?php

declare(strict_types=1);

use Modules\Jana\Entities\Mcp\McpMemoryDocument;
use Modules\Jana\Mcp\Tools\DecisionsSearchTool;

uses(Tests\TestCase::class);

/**
 * Resumo do decisions-search (fix 2026-06-29) — prioriza summary curado >
 * seção Decisão/Contexto > snippet posicional legado.
 *
 * Origem: medição empírica (N=30, busca real) mostrou 53% dos snippets caindo
 * em chunk posicional cego (1ª palavra da query ancorando no meio do doc), sem
 * resumir a decisão. Ex real: ADR 0093 buscada devolvia "### Garantia 1 — Schema"
 * em vez da decisão; ADR 0121 devolvia parágrafo sobre o cliente piloto.
 *
 * Teste PURO de método (sem banco): instancia o tool + Closure::bind nos protected.
 */
function callDecisionsSearchProtected(DecisionsSearchTool $tool, string $method, ...$args)
{
    $fn = function () use ($method, $args) {
        return $this->{$method}(...$args);
    };

    return Closure::bind($fn, $tool, DecisionsSearchTool::class)();
}

beforeEach(function () {
    $this->tool = new DecisionsSearchTool();
});

it('extrai o 1º parágrafo da seção Decisão (acentuada, não casa byte-a-byte errado)', function () {
    $body = "# ADR 0058 — Reverb substituído\n\n## Contexto\n\nReverb crashou no smoke.\n\n"
        . "## Decisão\n\n**Adotar Centrifugo + FrankenPHP** como stack realtime.\n\n## Consequências\n\nblah";

    $res = callDecisionsSearchProtected($this->tool, 'extrairResumoDecisao', $body);

    expect($res)->toContain('Adotar Centrifugo');
    // Decisão vence Contexto: o resumo NÃO pode ser o texto do Contexto.
    expect($res)->not->toContain('Reverb crashou');
});

it('cai pra Contexto quando não há seção Decisão e ignora sub-header posicional', function () {
    $body = "# ADR 0093 — Multi-tenant\n\n## Contexto\n\nUltimatePOS é multi-tenant por business_id.\n\n"
        . "### Garantia 1 — Schema obrigatório\n\nToda tabela tem business_id.";

    $res = callDecisionsSearchProtected($this->tool, 'extrairResumoDecisao', $body);

    expect($res)->toContain('multi-tenant por business_id');
    // O bug original: devolvia o sub-header "Garantia 1". Não pode mais.
    expect($res)->not->toContain('Garantia 1');
});

it('retorna null quando nenhuma seção canônica existe', function () {
    $body = "# Título\n\nsó um parágrafo solto, sem seções canônicas.";

    expect(callDecisionsSearchProtected($this->tool, 'extrairResumoDecisao', $body))->toBeNull();
});

it('pula blockquote de status e separador ao extrair o parágrafo', function () {
    $body = "## Decisão\n\n> **Status:** aceito\n\n---\n\nO texto real da decisão aqui.";

    $res = callDecisionsSearchProtected($this->tool, 'extrairResumoDecisao', $body);

    expect($res)->toBe('O texto real da decisão aqui.');
});

it('montarResumo prioriza o summary curado do frontmatter', function () {
    $doc = new McpMemoryDocument();
    $doc->metadata = ['summary' => 'Resumo curado da decisão.'];
    $doc->content_md = "## Decisão\n\nOutro texto que NÃO deve aparecer.";

    $res = callDecisionsSearchProtected($this->tool, 'montarResumo', $doc, 'qualquer', 240);

    expect($res)->toBe('Resumo curado da decisão.');
});

it('montarResumo usa a seção Decisão quando não há summary', function () {
    $doc = new McpMemoryDocument();
    $doc->content_md = "## Decisão\n\nAdotar a estratégia X.";

    $res = callDecisionsSearchProtected($this->tool, 'montarResumo', $doc, 'qualquer', 240);

    expect($res)->toContain('Adotar a estratégia X');
});

it('montarResumo preserva o fallback posicional legado quando o doc não tem seções', function () {
    $doc = new McpMemoryDocument();
    $doc->content_md = 'Texto livre sem seções, mencionando Centrifugo no meio do parágrafo.';

    $res = callDecisionsSearchProtected($this->tool, 'montarResumo', $doc, 'Centrifugo', 240);

    // Comportamento legado preservado: ancora na 1ª palavra da query.
    expect($res)->toContain('Centrifugo');
});

it('normalizarResumo remove markdown inline e converte links', function () {
    $res = callDecisionsSearchProtected(
        $this->tool,
        'normalizarResumo',
        'Veja **isto** e [a ADR](0093-x.md) com `code`.',
        240
    );

    expect($res)->toBe('Veja isto e a ADR com code.');
});

it('normalizarResumo trunca sem cortar caractere multibyte no meio', function () {
    $res = callDecisionsSearchProtected($this->tool, 'normalizarResumo', str_repeat('ção ', 100), 20);

    expect(mb_strlen($res))->toBeLessThanOrEqual(21); // 20 + reticência
    expect($res)->toEndWith('…');
    expect(mb_check_encoding($res, 'UTF-8'))->toBeTrue(); // não quebrou UTF-8
});
