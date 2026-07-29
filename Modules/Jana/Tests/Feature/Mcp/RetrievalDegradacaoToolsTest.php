<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Modules\Jana\Entities\Mcp\McpMemoryDocument;
use Modules\Jana\Mcp\Tools\MemoriaSearchTool;
use Modules\Jana\Support\RetrievalStatus;

uses(Tests\TestCase::class);

/**
 * DEGRADAÇÃO ≠ AUSÊNCIA nas outras duas portas (2026-07-28, 2ª onda).
 *
 * A 1ª onda (#4979) corrigiu só o `kb-answer`. A revisão pós-merge mostrou que
 * `decisions-search` e `memoria-search` tinham o MESMO defeito, na mesma forma:
 * caem no FULLTEXT quando o caminho semântico não responde e, se o plano B também
 * não acha, dizem "Nenhum ADR encontrado" / "Nenhum fato encontrado" — indistinguível
 * de índice fora do ar. O `decisions-search` é a tool canônica pra "qual ADR fala
 * sobre X", provavelmente a mais usada do dia a dia.
 *
 * O que estes testes provam (comportamento, não forma):
 *   - índice/pipeline NÃO respondendo ⇒ o texto declara indisponibilidade;
 *   - índice/pipeline respondendo vazio ⇒ mudo (senão o aviso vira ruído e ninguém lê);
 *   - o fallback continua acontecendo nos dois casos (nada de exceção nova).
 *
 * Sem grep de string no fonte: isso seria presence-gate (LC-11, banida em
 * proibicoes.md §5 2026-07-27) — mede a FORMA, não o COMPORTAMENTO.
 */

/** Fixa o host SEMPRE: sem isso o early-return de `host === ''` mascara o caminho medido (LC-13). */
function fixaMeiliParaTools(): void
{
    config()->set('scout.meilisearch.host', 'http://meili.test');
    config()->set('scout.meilisearch.key', 'k');
    config()->set('copiloto.mcp_search.docs_query_instruction', '');
    config()->set('copiloto.mcp_search.docs_pipeline', true);
}

// ── RetrievalStatus: a frase é uma só, e só fala quando deve ─────────────────────────

it('RetrievalStatus.aviso fala no degradado e cala no resto (bite-test + controle)', function () {
    expect(RetrievalStatus::aviso(true))->toContain('Busca semântica indisponível')
        ->and(RetrievalStatus::aviso(false))->toBe('');
});

it('a frase do kb-answer e a das outras tools são LITERALMENTE a mesma', function () {
    // Se alguém reescrever "quase igual" num dos lados, isto quebra.
    expect(\Modules\Jana\Services\Kb\KbAnswerService::AVISO_DEGRADADO)
        ->toBe(RetrievalStatus::AVISO);
});

// ── decisions-search ────────────────────────────────────────────────────────────────

it('decisions-search: índice FORA + zero resultados ⇒ NÃO afirma ausência de ADR', function () {
    fixaMeiliParaTools();
    Http::fake(['http://meili.test/*' => Http::response('', 503)]);

    $resposta = (string) app(\Modules\Jana\Mcp\Tools\DecisionsSearchTool::class)
        ->handle(new \Laravel\Mcp\Request(['query' => 'zzz termo improvável xyzq']))
        ->content();

    Http::assertSent(fn ($req) => str_starts_with($req->url(), 'http://meili.test'));
    expect($resposta)->toContain('Não posso afirmar que não exista ADR')
        ->and($resposta)->not->toContain('Nenhum ADR encontrado');
});

it('decisions-search: índice VIVO + zero resultados ⇒ resposta normal, sem alarme', function () {
    fixaMeiliParaTools();
    Http::fake(['http://meili.test/*' => Http::response(['hits' => []], 200)]);

    $resposta = (string) app(\Modules\Jana\Mcp\Tools\DecisionsSearchTool::class)
        ->handle(new \Laravel\Mcp\Request(['query' => 'zzz termo improvável xyzq']))
        ->content();

    expect($resposta)->toContain('Nenhum ADR encontrado')
        ->and($resposta)->not->toContain('Busca semântica indisponível');
});

it('decisions-search: include_archived nunca marca degradado (FULLTEXT por desenho)', function () {
    fixaMeiliParaTools();
    Http::fake(['http://meili.test/*' => Http::response('', 503)]);

    $resposta = (string) app(\Modules\Jana\Mcp\Tools\DecisionsSearchTool::class)
        ->handle(new \Laravel\Mcp\Request(['query' => 'zzz termo improvável xyzq', 'include_archived' => true]))
        ->content();

    // Nem chega a consultar o índice — logo não há degradação a declarar.
    Http::assertNothingSent();
    expect($resposta)->not->toContain('Busca semântica indisponível');
});

// ── memoria-search: os 3 `null` deixaram de ser um só ───────────────────────────────

it('memoria-search: driver SEM suporte ⇒ PIPELINE_NAO_SUPORTADO (desenho, não degradação)', function () {
    // Driver que NÃO é MeilisearchDriver — injetado explicitamente. A 1ª versão deste
    // teste dependia do driver "de dev/CI" resolvido por config e falhou no CT 100,
    // onde o container resolve o Meilisearch REAL: veio `vazio` (correto) em vez de
    // `nao_suportado`. O código estava certo; a premissa do teste sobre o ambiente é
    // que estava errada. Agora o caminho medido não depende de onde o teste roda.
    app()->instance(
        \Modules\Jana\Contracts\MemoriaContrato::class,
        Mockery::mock(\Modules\Jana\Contracts\MemoriaContrato::class)
    );

    $tool = new class extends MemoriaSearchTool
    {
        public function expor(int $b, string $q, int $l, ?string &$status = null): ?\Laravel\Mcp\Response
        {
            return $this->buscarViaPipeline($b, $q, $l, $status);
        }
    };

    $status = null;
    expect($tool->expor(1, 'x', 3, $status))->toBeNull()
        ->and($status)->toBe(MemoriaSearchTool::PIPELINE_NAO_SUPORTADO)
        ->and($status)->not->toBe(MemoriaSearchTool::PIPELINE_DEGRADADO);
});

it('memoria-search: driver que LANÇA ⇒ PIPELINE_DEGRADADO (e não propaga a exceção)', function () {
    $driver = Mockery::mock(\Modules\Jana\Services\Memoria\MeilisearchDriver::class);
    $driver->shouldReceive('buscarBusiness')->andThrow(new \RuntimeException('meili fora'));
    app()->instance(\Modules\Jana\Contracts\MemoriaContrato::class, $driver);

    $tool = new class extends MemoriaSearchTool
    {
        public function expor(int $b, string $q, int $l, ?string &$status = null): ?\Laravel\Mcp\Response
        {
            return $this->buscarViaPipeline($b, $q, $l, $status);
        }
    };

    $status = null;
    expect($tool->expor(1, 'x', 3, $status))->toBeNull()
        ->and($status)->toBe(MemoriaSearchTool::PIPELINE_DEGRADADO);
});

it('memoria-search: pipeline VIVO e vazio ⇒ PIPELINE_VAZIO, não degradado', function () {
    $driver = Mockery::mock(\Modules\Jana\Services\Memoria\MeilisearchDriver::class);
    $driver->shouldReceive('buscarBusiness')->andReturn([]);
    app()->instance(\Modules\Jana\Contracts\MemoriaContrato::class, $driver);

    $tool = new class extends MemoriaSearchTool
    {
        public function expor(int $b, string $q, int $l, ?string &$status = null): ?\Laravel\Mcp\Response
        {
            return $this->buscarViaPipeline($b, $q, $l, $status);
        }
    };

    $status = null;
    expect($tool->expor(1, 'x', 3, $status))->toBeNull()
        ->and($status)->toBe(MemoriaSearchTool::PIPELINE_VAZIO)
        ->and(RetrievalStatus::aviso($status === MemoriaSearchTool::PIPELINE_DEGRADADO))->toBe('');
});

it('memoria-search: assinatura de 3 args segue intacta (chamador antigo não muda)', function () {
    $tool = new class extends MemoriaSearchTool
    {
        public function expor(int $b, string $q, int $l): ?\Laravel\Mcp\Response
        {
            return $this->buscarViaPipeline($b, $q, $l); // sem o 4º parâmetro
        }
    };

    expect($tool->expor(1, 'x', 3))->toBeNull();
});
