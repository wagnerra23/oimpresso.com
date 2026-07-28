<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Modules\Jana\Entities\Mcp\McpMemoryDocument;
use Modules\Jana\Services\Kb\KbAnswerService;

uses(Tests\TestCase::class);

/**
 * DEGRADAÇÃO ≠ AUSÊNCIA no retrieval da KB (2026-07-28).
 *
 * Antes desta mudança, QUATRO situações devolviam a mesma Collection vazia de
 * `buscarHybrid` — host não-configurado, exceção de rede, HTTP≠2xx e zero hits — e o
 * `kb-answer` respondia "Não encontrei nada conclusivo · Confiança: baixa" em todas.
 * Índice fora do ar ficava indistinguível de "a KB não tem o assunto" pra quem lia a
 * resposta; a única pista era um warning em log, que ninguém consulta pra julgar
 * confiança de resposta.
 *
 * O que estes testes provam (comportamento, não forma):
 *   - o índice RESPONDENDO vazio é `HYBRID_VAZIO` — e NÃO contamina a resposta com
 *     aviso de indisponibilidade (é o controle negativo que impede o alarme de virar
 *     ruído constante);
 *   - o índice NÃO respondendo é `HYBRID_INDISPONIVEL` nos 3 modos de falha;
 *   - o fallback pro FULLTEXT continua acontecendo NOS DOIS casos (disponibilidade é
 *     escolha deliberada — a mudança torna a degradação visível, não fatal);
 *   - a assinatura antiga (6 args, como `DecisionsSearchTool` chama) segue intacta.
 *
 * Sem assert de status por grep no fonte: isso seria presence-gate (classe LC-11,
 * banida em proibicoes.md §5 2026-07-27) — mede a FORMA, não o COMPORTAMENTO.
 *
 * @see Modules/Jana/Entities/Mcp/McpMemoryDocument.php::buscarHybrid
 * @see Modules/Jana/Services/Kb/KbAnswerService.php::retrieve
 */

/**
 * Fixa o host SEMPRE. Sem isto, `buscarHybrid` pode retornar no early-return de
 * `host === ''` e o teste "falha de rede" passaria VERDE sem nunca exercer a falha
 * de rede — verde por não-execução (LC-13). Aqui o caminho medido é o pretendido.
 */
function fixaHostMeili(): void
{
    config()->set('scout.meilisearch.host', 'http://meili.test');
    config()->set('scout.meilisearch.key', 'k');
    config()->set('copiloto.mcp_search.docs_query_instruction', ''); // sem Ollama no meio
}

it('marca HYBRID_VAZIO quando o índice RESPONDE sem hits (ausência legítima, não degradação)', function () {
    fixaHostMeili();
    Http::fake(['http://meili.test/*' => Http::response(['hits' => []], 200)]);

    $status = null;
    $r = McpMemoryDocument::buscarHybrid('assunto inexistente', 5, null, null, null, 0, $status);

    expect($r)->toBeEmpty()
        ->and($status)->toBe(McpMemoryDocument::HYBRID_VAZIO)
        ->and($status)->not->toBe(McpMemoryDocument::HYBRID_INDISPONIVEL);
});

it('marca HYBRID_INDISPONIVEL quando o Meilisearch devolve HTTP de erro', function () {
    fixaHostMeili();
    Http::fake(['http://meili.test/*' => Http::response('', 503)]);

    $status = null;
    $r = McpMemoryDocument::buscarHybrid('isolamento multi-tenant', 5, null, null, null, 0, $status);

    // Prova que o 503 foi de fato exercido — não um early-return silencioso.
    Http::assertSent(fn ($req) => str_starts_with($req->url(), 'http://meili.test'));
    expect($r)->toBeEmpty()
        ->and($status)->toBe(McpMemoryDocument::HYBRID_INDISPONIVEL);
});

it('marca HYBRID_INDISPONIVEL quando a conexão com o Meilisearch estoura (exceção)', function () {
    fixaHostMeili();
    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('connect timeout');
    });

    $status = null;
    $r = McpMemoryDocument::buscarHybrid('isolamento multi-tenant', 5, null, null, null, 0, $status);

    expect($r)->toBeEmpty()
        ->and($status)->toBe(McpMemoryDocument::HYBRID_INDISPONIVEL);
});

it('marca HYBRID_INDISPONIVEL quando o host do Meilisearch nem está configurado', function () {
    config()->set('scout.meilisearch.host', '');
    Http::fake();

    $status = null;
    $r = McpMemoryDocument::buscarHybrid('qualquer coisa', 5, null, null, null, 0, $status);

    expect($r)->toBeEmpty()
        ->and($status)->toBe(McpMemoryDocument::HYBRID_INDISPONIVEL);
    Http::assertNothingSent(); // config ausente: nem tenta a rede
});

it('mantém a assinatura antiga de 6 args intacta (DecisionsSearchTool não muda)', function () {
    fixaHostMeili();
    Http::fake(['http://meili.test/*' => Http::response(['hits' => []], 200)]);

    // Exatamente a forma chamada em DecisionsSearchTool.php:70 — sem o 7º parâmetro.
    $r = McpMemoryDocument::buscarHybrid('multi-tenant', 5, null, 'adr', null, 0);

    expect($r)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class)
        ->and($r)->toBeEmpty();
});

// ── helpers puros: o contrato do que o usuário VÊ (sem DB, sem rede, sem LLM) ────────

it('avisoDegradacao só fala quando o retrieval foi degradado (bite-test + controles)', function () {
    // MORDE no caso degradado…
    expect(KbAnswerService::avisoDegradacao(KbAnswerService::RETRIEVAL_FULLTEXT_DEGRADADO))
        ->toContain('Busca semântica indisponível')
        ->and(KbAnswerService::degradado(KbAnswerService::RETRIEVAL_FULLTEXT_DEGRADADO))->toBeTrue();

    // …e fica MUDO em todo o resto (senão o aviso vira ruído permanente e ninguém lê).
    expect(KbAnswerService::avisoDegradacao(KbAnswerService::RETRIEVAL_HYBRID))->toBe('')
        ->and(KbAnswerService::avisoDegradacao(KbAnswerService::RETRIEVAL_FULLTEXT))->toBe('')
        ->and(KbAnswerService::avisoDegradacao(null))->toBe('')
        ->and(KbAnswerService::degradado(KbAnswerService::RETRIEVAL_FULLTEXT))->toBeFalse()
        ->and(KbAnswerService::degradado(null))->toBeFalse();
});

// ── mapeamento hybrid → status do service ───────────────────────────────────────────
// Estes exercitam `retrieve()` inteiro, que termina no FULLTEXT (`MATCH ... AGAINST`)
// — sintaxe MySQL. O caminho canônico do projeto é o CT 100 (MySQL real); em driver
// sem FULLTEXT o teste FALHA em vez de pular silencioso.

it('flag ON + hybrid CAÍDO ⇒ RETRIEVAL_FULLTEXT_DEGRADADO (fallback acontece, mas visível)', function () {
    fixaHostMeili();
    config()->set('copiloto.mcp_search.docs_pipeline', true);
    Http::fake(['http://meili.test/*' => Http::response('', 500)]);

    $status = null;
    $docs = app(KbAnswerService::class)->retrieve(null, 'multi-tenant', 'all', '', 5, $status);

    expect($status)->toBe(KbAnswerService::RETRIEVAL_FULLTEXT_DEGRADADO)
        ->and(KbAnswerService::degradado($status))->toBeTrue()
        // fallback FULLTEXT continua rodando — degradação é visível, não fatal
        ->and($docs)->toBeInstanceOf(\Illuminate\Support\Collection::class);
});

it('flag ON + hybrid VAZIO ⇒ RETRIEVAL_FULLTEXT sem alarme (índice vivo, KB é que não tem)', function () {
    fixaHostMeili();
    config()->set('copiloto.mcp_search.docs_pipeline', true);
    Http::fake(['http://meili.test/*' => Http::response(['hits' => []], 200)]);

    $status = null;
    app(KbAnswerService::class)->retrieve(null, 'assunto inexistente', 'all', '', 5, $status);

    expect($status)->toBe(KbAnswerService::RETRIEVAL_FULLTEXT)
        ->and(KbAnswerService::degradado($status))->toBeFalse();
});

it('flag OFF ⇒ RETRIEVAL_FULLTEXT e o Meilisearch nem é consultado', function () {
    fixaHostMeili();
    config()->set('copiloto.mcp_search.docs_pipeline', false);
    Http::fake();

    $status = null;
    app(KbAnswerService::class)->retrieve(null, 'multi-tenant', 'all', '', 5, $status);

    expect($status)->toBe(KbAnswerService::RETRIEVAL_FULLTEXT)
        ->and(KbAnswerService::degradado($status))->toBeFalse();
    Http::assertNothingSent();
});
