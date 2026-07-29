<?php

declare(strict_types=1);

use Modules\KB\Services\Dtos\MetaSuggestion;
use Modules\KB\Services\Dtos\RagResult;
use Modules\KB\Services\Dtos\SummaryResult;
use Modules\KB\Services\KbCorpusBuilder;
use Modules\KB\Services\KbRagService;

/**
 * Degradação VISÍVEL no pipeline de IA do KB (levantamento 2026-07-28).
 *
 * @covers-us US-KB-003
 *   Cobre o contrato "o consumidor consegue distinguir base-sem-conteúdo de
 *   busca-que-não-funcionou". NÃO cobre qualidade de resposta (dono:
 *   KbRagasEvalTest / ADR 0318) nem isolamento multi-tenant (dono:
 *   KbRagServiceMultiTenantTest).
 *
 * O DEFEITO que estes testes travam:
 *   `KbCorpusBuilder::retrieve()` devolvia coleção vazia tanto quando o
 *   Meilisearch estava fora do ar quanto quando a busca legitimamente não
 *   achava nada. As duas caíam em `RagResult::notFound()` e saíam com HTTP 200
 *   idêntico — do lado de fora, "a base não tem isso" e "a busca não funcionou"
 *   eram a mesma resposta, e o leitor concluía que o conteúdo não existe.
 *
 * A escolha por DISPONIBILIDADE é deliberada e preservada: nada aqui exige 500.
 * O que se exige é que a degradação apareça no payload.
 *
 * Tier 0: biz=1 canônico — NUNCA biz=4 (ROTA LIVRE prod, ADR 0101).
 *
 * @see Modules/KB/Services/KbCorpusBuilder.php
 * @see Modules/KB/Services/KbRagService.php
 * @see memory/requisitos/KB/SCHEMA-DB-V1.md §11
 */

// ─── O coração do achado: mesma resposta, sinais diferentes ──────────────────

it('separa busca-vazia-legitima de retrieval-degradado (o defeito original)', function () {
    $vazioLegitimo = RagResult::notFound(120, 'hash-corpus');
    $degradado     = RagResult::notFound(120, 'hash-corpus', [
        KbCorpusBuilder::DEGRADED_RETRIEVAL_FAILED,
    ]);

    // Os dois continuam indistinguíveis no que o usuário lê — de propósito:
    // a mensagem e as fontes são decisão de produto, não deste contrato.
    expect($degradado->answer)->toBe($vazioLegitimo->answer);
    expect($degradado->sources)->toBe($vazioLegitimo->sources)->toBe([]);

    // Mas quem consome a API agora consegue separar os dois casos.
    expect($vazioLegitimo->degraded())->toBeFalse();
    expect($degradado->degraded())->toBeTrue();

    expect($vazioLegitimo->toArray()['meta']['degraded'])->toBeFalse();
    expect($degradado->toArray()['meta']['degraded'])->toBeTrue();
    expect($degradado->toArray()['meta']['degradation'])
        ->toContain(KbCorpusBuilder::DEGRADED_RETRIEVAL_FAILED);
});

it('marca o caso mais enganoso: LLM caiu DEPOIS de achar fontes', function () {
    // Retrieval funcionou (achou fontes), o LLM é que morreu. A resposta devolvida
    // é a mesma de "não encontrei" — sem o sinal, o leitor conclui que o KB não
    // tem o assunto, quando tem e foi encontrado.
    $llmCaiu = RagResult::notFound(300, 'hash', [KbRagService::DEGRADED_LLM_FAILED]);

    expect($llmCaiu->degraded())->toBeTrue();
    expect($llmCaiu->toArray()['meta']['degradation'])
        ->toContain(KbRagService::DEGRADED_LLM_FAILED);
});

// ─── Bite-test no builder real (não só no DTO) ───────────────────────────────

it('builder recem-criado NAO esta degradado (controle negativo)', function () {
    $corpus = new KbCorpusBuilder(1);

    expect($corpus->degraded())->toBeFalse();
    expect($corpus->degradations())->toBe([]);
});

it('retrieve com Meilisearch inalcancavel devolve vazio E marca degradacao', function () {
    // Porta 1 recusa conexão imediatamente (sem timeout longo).
    config(['scout.meilisearch.host' => 'http://127.0.0.1:1']);
    config(['scout.meilisearch.key'  => '']);

    $corpus = new KbCorpusBuilder(1);
    $hits   = $corpus->retrieve('qualquer pergunta sobre governanca', 5);

    // Vazio como antes — a disponibilidade não regride.
    expect($hits->isEmpty())->toBeTrue();

    // ...mas agora com sinal. Duas razões possíveis conforme o runtime tenha ou
    // não o cliente Meilisearch instalado; ambas são degradação de retrieval,
    // e o que este teste trava é que NENHUMA das duas passa em silêncio.
    expect($corpus->degraded())->toBeTrue();
    expect($corpus->degradations())->toBeArray()->not->toBeEmpty();
    expect($corpus->degradations()[0])->toBeIn([
        KbCorpusBuilder::DEGRADED_RETRIEVAL_FAILED,
        KbCorpusBuilder::DEGRADED_RETRIEVAL_UNAVAILABLE,
    ]);
});

it('marcacao de degradacao nao duplica a mesma razao', function () {
    config(['scout.meilisearch.host' => 'http://127.0.0.1:1']);

    $corpus = new KbCorpusBuilder(1);
    $corpus->retrieve('pergunta a', 5);
    $corpus->retrieve('pergunta b', 5);

    expect(count($corpus->degradations()))->toBe(count(array_unique($corpus->degradations())));
});

// ─── summarize e suggestMeta: mesmo padrão, mesmos DTOs ──────────────────────

it('SummaryResult distingue TL;DR real de fallback por LLM caido', function () {
    $real = new SummaryResult(
        tldr: 'Resumo de verdade.',
        bulletPoints: ['a', 'b'],
        audienceHint: null,
        sourceNodeId: 1,
        sourceSlug: 'algum-slug',
        sourceType: 'adr',
        latencyMs: 10,
        tokensIn: 100,
        tokensOut: 50,
        costEstimatedBrl: 0.0,
    );

    // Fallback: quando o node TEM excerpt, este texto é plausível e nada indicava
    // que a IA não rodou.
    $fallback = new SummaryResult(
        tldr: 'Excerpt do proprio node, que parece um resumo.',
        bulletPoints: [],
        audienceHint: null,
        sourceNodeId: 1,
        sourceSlug: 'algum-slug',
        sourceType: 'adr',
        latencyMs: 10,
        tokensIn: 0,
        tokensOut: 0,
        costEstimatedBrl: 0.0,
        degradations: [KbRagService::DEGRADED_LLM_FAILED],
    );

    expect($real->degraded())->toBeFalse();
    expect($fallback->degraded())->toBeTrue();
    expect($fallback->toArray()['meta']['degraded'])->toBeTrue();
});

it('MetaSuggestion distingue rascunho pobre de IA caida', function () {
    // Rascunho sem conteúdo: sugestão vazia, mas o pipeline funcionou.
    $rascunhoPobre = new MetaSuggestion('', '', [], null, null, 5, 0, 0, 0.0);

    // IA caiu: sugestão igualmente vazia — antes, indistinguível do caso acima.
    $iaCaiu = new MetaSuggestion('', 'trecho cru', [], null, null, 5, 0, 0, 0.0, [
        KbRagService::DEGRADED_LLM_FAILED,
    ]);

    expect($rascunhoPobre->degraded())->toBeFalse();
    expect($iaCaiu->degraded())->toBeTrue();
    expect($iaCaiu->toArray()['meta']['degradation'])
        ->toContain(KbRagService::DEGRADED_LLM_FAILED);
});

// ─── Back-compat: o contrato antigo do payload não muda ──────────────────────

it('toArray dos 3 DTOs preserva todas as chaves anteriores (aditivo)', function () {
    $rag = RagResult::notFound(1, 'h')->toArray();
    expect(array_keys($rag))->toBe(['answer', 'sources', 'meta']);
    foreach (['latency_ms', 'tokens_in', 'tokens_out', 'cost_estimated_brl',
              'confidence', 'corpus_version_hash', 'cache_hit'] as $chave) {
        expect($rag['meta'])->toHaveKey($chave);
    }

    $sum = (new SummaryResult('t', [], null, 1, 's', 'adr', 1, 0, 0, 0.0))->toArray();
    expect(array_keys($sum))->toBe(['tldr', 'bullet_points', 'audience_hint', 'source', 'meta']);
    foreach (['latency_ms', 'tokens_in', 'tokens_out', 'cost_estimated_brl', 'cache_hit'] as $chave) {
        expect($sum['meta'])->toHaveKey($chave);
    }

    $meta = (new MetaSuggestion('t', 'e', [], null, null, 1, 0, 0, 0.0))->toArray();
    expect(array_keys($meta))->toBe(['title', 'excerpt', 'tags', 'category_slug', 'nivel', 'meta']);
    foreach (['latency_ms', 'tokens_in', 'tokens_out', 'cost_estimated_brl'] as $chave) {
        expect($meta['meta'])->toHaveKey($chave);
    }
});

it('degradations default vazio mantem construtores existentes validos', function () {
    // Todos os call-sites anteriores omitiam o parâmetro novo — não podem quebrar.
    $r = new RagResult('a', [], 1, 0, 0, 0.0);

    expect($r->degradations)->toBe([]);
    expect($r->degraded())->toBeFalse();
});
