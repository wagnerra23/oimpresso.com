<?php

declare(strict_types=1);

namespace Modules\KB\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Jana\Ai\Agents\KbAnswerAgent;
use Modules\Jana\Entities\Mcp\McpMemoryDocument;
use Modules\Jana\Services\Privacy\PiiRedactor;
use Modules\KB\Entities\KbNode;
use Modules\KB\Services\Dtos\MetaSuggestion;
use Modules\KB\Services\Dtos\RagResult;
use Modules\KB\Services\Dtos\SummaryResult;

/**
 * KbRagService — service principal de IA do KB Unificado (ONDA 4).
 *
 * 3 capacidades canônicas (SCHEMA-DB-V1 §11):
 *   1. ask($query, $businessId, $userId?, $opts) → RagResult
 *      Pergunta livre sobre o KB. RAG flow: retrieve → re-rank → prompt LLM → parse → cite.
 *
 *   2. summarize($nodeSlug, $businessId) → SummaryResult
 *      Resumo TL;DR de 1 node específico (ADR longo, session log denso, runbook).
 *
 *   3. suggestMeta($bodyBlocks, $businessId) → MetaSuggestion
 *      Auto-tag (title/excerpt/tags) a partir do rascunho do composer.
 *
 * Pattern (ADR 0035 + ADR 0048):
 *   - REUSA laravel/ai SDK via KbAnswerAgent (Modules/Jana/Ai/Agents/) — NÃO criar provider novo.
 *   - Retrieval: KbCorpusBuilder → Meilisearch hybrid embedder (ADR 0036).
 *   - Re-rank: top-K → top-N por score Meilisearch + bonus de tipo (charter > ADR > session).
 *   - Citações: numeração [1][2][3] no prompt; resposta DEVE referenciar índices.
 *
 * Multi-tenant Tier 0 (ADR 0093):
 *   - $businessId é parâmetro EXPLÍCITO em todos os métodos públicos
 *   - Nunca usa session() em código de service (ADR 0093 §4)
 *   - Re-aplicado no filtro Meilisearch + queries Eloquent
 *
 * Custo monitorado (audit em mcp_audit_log):
 *   - Cada chamada loga tokens_in + tokens_out + cost_estimated_brl + corpus_hash
 *   - Cache Redis TTL 1h pra ask() — key = hash(query + businessId + corpus_hash)
 *   - Cache TTL 6h pra summarize() — key = nodeSlug + businessId + updated_at
 *
 * @see memory/requisitos/KB/SCHEMA-DB-V1.md §11
 * @see memory/requisitos/KB/BRIEFING.md
 * @see memory/decisions/proposals/0149-kb-unificado-grafo-conhecimento-modulo-ia-central.md
 * @see memory/decisions/0035-stack-ai-canonica-wagner-2026-04-26.md
 * @see memory/decisions/0048-framework-agentes-laravel-ai-vizra-rejeitada.md
 */
class KbRagService
{
    /**
     * Top-N final passado pro prompt do LLM.
     */
    public const PROMPT_TOP_N = KbCorpusBuilder::PROMPT_TOP_N;

    /**
     * TTL do cache Redis pra respostas ask().
     */
    public const CACHE_ASK_TTL_S = 3600;          // 1h

    /**
     * TTL do cache Redis pra summarize().
     */
    public const CACHE_SUMMARIZE_TTL_S = 21600;   // 6h

    /**
     * USD → BRL aproximado pra custo estimado.
     * Configurável via config('kb.usd_to_brl', 5.0).
     */
    public const USD_TO_BRL_FALLBACK = 5.0;

    /**
     * Preços por 1M tokens (gpt-4o-mini, ADR 0035 Brain A).
     * Atualizar quando mudar modelo default.
     */
    public const PRICE_INPUT_PER_M_USD  = 0.15;
    public const PRICE_OUTPUT_PER_M_USD = 0.60;

    public function __construct(
        // Service não recebe businessId no constructor — métodos públicos
        // recebem explicitamente pra evitar dependência de container scope.
    ) {}

    // ==================================================================
    // 1. ASK — RAG sobre o corpus completo
    // ==================================================================

    /**
     * Pergunta livre sobre o KB. RAG flow completo.
     *
     * Flow:
     *   1) Sanitize query (PII redact — usuário pode digitar CPF acidentalmente)
     *   2) Calcula corpus version hash
     *   3) Tenta cache Redis (key = hash(query + biz + corpus_hash))
     *   4) Retrieve top-K nodes via Meilisearch (KbCorpusBuilder)
     *   5) Re-rank: top-K → top-N por score + bonus de tipo
     *   6) Monta prompt "FONTES" numerado [1][2][3]…
     *   7) Call KbAnswerAgent (laravel/ai SDK)
     *   8) Parse resposta + extract citações
     *   9) Grava cache
     *   10) Retorna RagResult
     *
     * @param  array{idempotency_key?:string,top_n?:int,bypass_cache?:bool}  $opts
     */
    public function ask(string $query, int $businessId, ?int $userId = null, array $opts = []): RagResult
    {
        $this->assertBusinessId($businessId);
        $startedAt = microtime(true);

        $query = trim($query);
        if ($query === '') {
            return RagResult::notFound(0);
        }

        // 1) PII redact — antes de qualquer log/cache/LLM
        $querySanitized = $this->redactPii($query);

        // 2) Corpus version hash
        $corpus = new KbCorpusBuilder($businessId);
        $corpusHash = $corpus->corpusVersionHash();

        // 3) Cache check
        $cacheKey = $this->askCacheKey($querySanitized, $businessId, $corpusHash, $opts);
        $bypassCache = (bool) ($opts['bypass_cache'] ?? false);

        if (! $bypassCache && ($cached = Cache::get($cacheKey))) {
            if ($cached instanceof RagResult) {
                $latency = $this->latencyMs($startedAt);
                return new RagResult(
                    answer: $cached->answer,
                    sources: $cached->sources,
                    latencyMs: $latency,
                    tokensIn: 0,
                    tokensOut: 0,
                    costEstimatedBrl: 0.0,
                    confidence: $cached->confidence,
                    corpusVersionHash: $corpusHash,
                    cacheHit: true,
                );
            }
        }

        // 4) Retrieve top-K
        $topK = $corpus->retrieve($querySanitized, KbCorpusBuilder::RETRIEVE_TOP_K);
        if ($topK->isEmpty()) {
            return RagResult::notFound($this->latencyMs($startedAt), $corpusHash);
        }

        // 5) Re-rank → top-N
        $topN = $this->rerank($topK, $opts['top_n'] ?? self::PROMPT_TOP_N);

        // 6+7+8) Prompt + LLM + parse
        $fontesText = $this->renderFontesBlock($topN);
        $agent = new KbAnswerAgent(
            pergunta: $querySanitized,
            fontes: $fontesText,
            maxCitacoes: min($topN->count(), 5),
        );

        try {
            $response = $agent->prompt($agent->montarPrompt());
            $rawText  = (string) $response;

            $tokensIn  = (int) ($response->usage->promptTokens ?? 0);
            $tokensOut = (int) ($response->usage->completionTokens ?? 0);
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->error('KbRagService::ask LLM falhou', [
                'business_id' => $businessId,
                'user_id'     => $userId,
                'error'       => $e->getMessage(),
            ]);

            return RagResult::notFound($this->latencyMs($startedAt), $corpusHash);
        }

        $parsed = $this->parseAgentResponse($rawText);

        // Sources finais: ordem do top-N + score do Meilisearch
        $sources = $topN->map(function (array $hit) {
            return [
                'kb_node_id' => $hit['kb_node_id'],
                'slug'       => $hit['slug'],
                'type'       => $hit['type'],
                'title'      => $hit['title'],
                'snippet'    => $hit['snippet'],
                'score'      => round($hit['score'], 4),
            ];
        })->values()->all();

        $cost = $this->estimateCostBrl($tokensIn, $tokensOut);

        $result = new RagResult(
            answer: $parsed['answer'],
            sources: $sources,
            latencyMs: $this->latencyMs($startedAt),
            tokensIn: $tokensIn,
            tokensOut: $tokensOut,
            costEstimatedBrl: $cost,
            confidence: $parsed['confidence'],
            corpusVersionHash: $corpusHash,
            cacheHit: false,
        );

        // 9) Cache write
        if (! $bypassCache && ! empty($sources)) {
            Cache::put($cacheKey, $result, self::CACHE_ASK_TTL_S);
        }

        Log::channel('copiloto-ai')->info('KbRagService::ask', [
            'business_id'        => $businessId,
            'user_id'            => $userId,
            'sources_count'      => count($sources),
            'tokens_in'          => $tokensIn,
            'tokens_out'         => $tokensOut,
            'cost_brl'           => $cost,
            'latency_ms'         => $result->latencyMs,
            'corpus_hash'        => substr($corpusHash, 0, 12),
            'confidence'         => $result->confidence,
        ]);

        return $result;
    }

    // ==================================================================
    // 2. SUMMARIZE — resumo TL;DR de 1 node
    // ==================================================================

    /**
     * Resumo TL;DR de 1 node específico.
     *
     * Para bridges canon (ADR/session/charter/...), lê body do mcp_memory_documents.
     * Para artigos editáveis, serializa body_blocks.
     */
    public function summarize(string $nodeSlug, int $businessId): SummaryResult
    {
        $this->assertBusinessId($businessId);
        $startedAt = microtime(true);

        $node = KbNode::query()
            ->withoutGlobalScopes() // SUPERADMIN: scope manual abaixo
            ->where('business_id', $businessId)
            ->where('slug', $nodeSlug)
            ->whereNull('deleted_at')
            ->with('sourceDoc')
            ->first();

        if (! $node) {
            throw new \InvalidArgumentException(
                "KbNode com slug '{$nodeSlug}' não encontrado em business {$businessId}."
            );
        }

        // Cache key inclui updated_at do node + (se bridge) updated_at do mcp_memory_documents
        $cacheKey = $this->summarizeCacheKey($nodeSlug, $businessId, $node);
        if ($cached = Cache::get($cacheKey)) {
            if ($cached instanceof SummaryResult) {
                return new SummaryResult(
                    tldr: $cached->tldr,
                    bulletPoints: $cached->bulletPoints,
                    audienceHint: $cached->audienceHint,
                    sourceNodeId: $cached->sourceNodeId,
                    sourceSlug: $cached->sourceSlug,
                    sourceType: $cached->sourceType,
                    latencyMs: $this->latencyMs($startedAt),
                    tokensIn: 0,
                    tokensOut: 0,
                    costEstimatedBrl: 0.0,
                    cacheHit: true,
                );
            }
        }

        $body = $node->renderContent();
        $body = $this->redactPii($body);
        $body = mb_substr($body, 0, 12000); // ~3k tokens — cap pra controlar custo

        // Prompt single-shot. Reusa pattern do KbAnswerAgent simplificado.
        $prompt = $this->buildSummarizePrompt($node, $body);

        try {
            // Usa KbAnswerAgent com instruções override — single-shot
            $agent = new KbAnswerAgent(
                pergunta: "Resuma este documento em TL;DR + 3-5 bullets.",
                fontes: $prompt,
                maxCitacoes: 0,
            );

            $response = $agent->prompt($agent->montarPrompt());
            $rawText  = (string) $response;

            $tokensIn  = (int) ($response->usage->promptTokens ?? 0);
            $tokensOut = (int) ($response->usage->completionTokens ?? 0);
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->error('KbRagService::summarize LLM falhou', [
                'node_slug'   => $nodeSlug,
                'business_id' => $businessId,
                'error'       => $e->getMessage(),
            ]);
            // Degradação: retorna excerpt do próprio node + sem bullets
            return new SummaryResult(
                tldr: (string) ($node->excerpt ?? 'Resumo indisponível no momento.'),
                bulletPoints: [],
                audienceHint: null,
                sourceNodeId: (int) $node->id,
                sourceSlug: $node->slug,
                sourceType: $node->type,
                latencyMs: $this->latencyMs($startedAt),
                tokensIn: 0,
                tokensOut: 0,
                costEstimatedBrl: 0.0,
            );
        }

        $parsed = $this->parseSummarizeResponse($rawText);
        $cost = $this->estimateCostBrl($tokensIn, $tokensOut);

        $result = new SummaryResult(
            tldr: $parsed['tldr'],
            bulletPoints: $parsed['bullets'],
            audienceHint: $parsed['audience'],
            sourceNodeId: (int) $node->id,
            sourceSlug: $node->slug,
            sourceType: $node->type,
            latencyMs: $this->latencyMs($startedAt),
            tokensIn: $tokensIn,
            tokensOut: $tokensOut,
            costEstimatedBrl: $cost,
        );

        Cache::put($cacheKey, $result, self::CACHE_SUMMARIZE_TTL_S);

        Log::channel('copiloto-ai')->info('KbRagService::summarize', [
            'node_slug'   => $nodeSlug,
            'business_id' => $businessId,
            'tokens_in'   => $tokensIn,
            'tokens_out'  => $tokensOut,
            'cost_brl'    => $cost,
            'latency_ms'  => $result->latencyMs,
        ]);

        return $result;
    }

    // ==================================================================
    // 3. SUGGEST META — auto-tag a partir de body_blocks rascunho
    // ==================================================================

    /**
     * @param  array<int,array<string,mixed>>  $bodyBlocks
     */
    public function suggestMeta(array $bodyBlocks, int $businessId): MetaSuggestion
    {
        $this->assertBusinessId($businessId);
        $startedAt = microtime(true);

        // KbCorpusBuilder::serializeBlocks é protected — replicamos inline.
        $bodyText = $this->serializeBlocksInline($bodyBlocks);
        $bodyText = $this->redactPii($bodyText);
        $bodyText = mb_substr($bodyText, 0, 8000);

        if (trim($bodyText) === '') {
            return new MetaSuggestion(
                title: '',
                excerpt: '',
                tags: [],
                categorySlug: null,
                nivel: null,
                latencyMs: $this->latencyMs($startedAt),
                tokensIn: 0,
                tokensOut: 0,
                costEstimatedBrl: 0.0,
            );
        }

        $prompt = $this->buildSuggestMetaPrompt($bodyText);

        try {
            $agent = new KbAnswerAgent(
                pergunta: 'Sugira meta (title, excerpt, tags, category, nivel) deste rascunho.',
                fontes: $prompt,
                maxCitacoes: 0,
            );

            $response = $agent->prompt($agent->montarPrompt());
            $rawText  = (string) $response;

            $tokensIn  = (int) ($response->usage->promptTokens ?? 0);
            $tokensOut = (int) ($response->usage->completionTokens ?? 0);
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->error('KbRagService::suggestMeta LLM falhou', [
                'business_id' => $businessId,
                'error'       => $e->getMessage(),
            ]);

            return new MetaSuggestion(
                title: '',
                excerpt: mb_substr($bodyText, 0, 280),
                tags: [],
                categorySlug: null,
                nivel: null,
                latencyMs: $this->latencyMs($startedAt),
                tokensIn: 0,
                tokensOut: 0,
                costEstimatedBrl: 0.0,
            );
        }

        $parsed = $this->parseSuggestMetaResponse($rawText, $bodyText);
        $cost = $this->estimateCostBrl($tokensIn, $tokensOut);

        Log::channel('copiloto-ai')->info('KbRagService::suggestMeta', [
            'business_id' => $businessId,
            'tokens_in'   => $tokensIn,
            'tokens_out'  => $tokensOut,
            'cost_brl'    => $cost,
            'latency_ms'  => $this->latencyMs($startedAt),
        ]);

        return new MetaSuggestion(
            title: $parsed['title'],
            excerpt: $parsed['excerpt'],
            tags: $parsed['tags'],
            categorySlug: $parsed['category_slug'],
            nivel: $parsed['nivel'],
            latencyMs: $this->latencyMs($startedAt),
            tokensIn: $tokensIn,
            tokensOut: $tokensOut,
            costEstimatedBrl: $cost,
        );
    }

    // ==================================================================
    // Internals — retrieval, prompt, parse, cache, custo
    // ==================================================================

    /**
     * Re-rank top-K → top-N. Critério V1:
     *   - Score base do Meilisearch
     *   - Bonus +0.15 se type ∈ {charter,adr,runbook}  (governança canon)
     *   - Bonus +0.10 se type = 'article'              (operacional Larissa)
     *   - Penalty -0.05 se snippet vazio
     *
     * @param  Collection<int,array<string,mixed>>  $topK
     * @return Collection<int,array<string,mixed>>
     */
    protected function rerank(Collection $topK, int $topN): Collection
    {
        return $topK
            ->map(function (array $hit) {
                $score = (float) ($hit['score'] ?? 0.0);
                $type  = (string) ($hit['type'] ?? 'reference');

                if (in_array($type, ['charter', 'adr', 'runbook'], true)) {
                    $score += 0.15;
                } elseif ($type === 'article') {
                    $score += 0.10;
                }

                if (trim((string) ($hit['snippet'] ?? '')) === '') {
                    $score -= 0.05;
                }

                $hit['score'] = $score;
                return $hit;
            })
            ->sortByDesc('score')
            ->values()
            ->take($topN);
    }

    /**
     * Renderiza bloco "FONTES" do prompt com numeração [1][2][3].
     *
     * @param  Collection<int,array<string,mixed>>  $topN
     */
    protected function renderFontesBlock(Collection $topN): string
    {
        $linhas = [];
        $i = 1;
        foreach ($topN as $hit) {
            $title = $hit['title'] ?? '(sem título)';
            $type  = $hit['type']  ?? 'reference';
            $slug  = $hit['slug']  ?? '';
            $snippet = trim((string) ($hit['snippet'] ?? $hit['excerpt'] ?? ''));
            if ($snippet === '') {
                $snippet = '(sem snippet)';
            }

            $linhas[] = "[{$i}] {$type} · {$title} (slug: {$slug})\n    {$snippet}";
            $i++;
        }

        return implode("\n\n", $linhas);
    }

    /**
     * Parse output canônico do KbAnswerAgent:
     *   Resposta: <texto>
     *   Citações:
     *   - [slug](path) — quote
     *   Confiança: alta|média|baixa
     *
     * Fail-open: se shape inválido, retorna texto cru como answer + confiança baixa.
     *
     * @return array{answer:string, confidence:string}
     */
    protected function parseAgentResponse(string $raw): array
    {
        $raw = trim($raw);

        // Confidence
        $confidence = 'media';
        if (preg_match('/Confian[çc]a:\s*(alta|m[eé]dia|baixa)/iu', $raw, $m)) {
            $c = mb_strtolower($m[1]);
            $confidence = match (true) {
                str_starts_with($c, 'alta')  => 'alta',
                str_starts_with($c, 'baix')  => 'baixa',
                default                       => 'media',
            };
        }

        // Answer (tudo até "Citações:" ou "Confiança:" exclusivo)
        $answer = $raw;
        if (preg_match('/Resposta:\s*(.+?)(?:\n\s*Cita[çc][õo]es:|\n\s*Confian[çc]a:|$)/su', $raw, $m)) {
            $answer = trim($m[1]);
        }

        return [
            'answer'     => $answer !== '' ? $answer : $raw,
            'confidence' => $confidence,
        ];
    }

    protected function buildSummarizePrompt(KbNode $node, string $body): string
    {
        return "DOCUMENTO PRA RESUMIR:\n\nTipo: {$node->type}\nTítulo: {$node->title}\nSlug: {$node->slug}\n\n---\n\n{$body}\n\n---\n\nResponda APENAS no formato:\n\nResposta: TL;DR em 1-2 frases · BULLETS: ponto 1 | ponto 2 | ponto 3 (3-5 bullets separados por ' | ') · AUDIENCE: Wagner governança | Larissa operacional | qualquer\nConfiança: alta|media|baixa";
    }

    /**
     * @return array{tldr:string, bullets:array<int,string>, audience:?string}
     */
    protected function parseSummarizeResponse(string $raw): array
    {
        $raw = trim($raw);

        $tldr = '';
        $bullets = [];
        $audience = null;

        // Resposta: TL;DR ... BULLETS: ... AUDIENCE: ...
        if (preg_match('/Resposta:\s*(.*?)(?:\n\s*Confian[çc]a:|$)/su', $raw, $m)) {
            $payload = trim($m[1]);

            // TL;DR vai do início até "BULLETS:" (ou todo o texto se não houver)
            if (preg_match('/^(.*?)(?:·\s*BULLETS:|BULLETS:)/su', $payload, $tm)) {
                $tldr = trim(rtrim($tm[1], '·'));
            } else {
                $tldr = $payload;
            }

            // BULLETS: a | b | c
            if (preg_match('/BULLETS:\s*(.*?)(?:·\s*AUDIENCE:|AUDIENCE:|$)/su', $payload, $bm)) {
                $bulletsRaw = trim(rtrim($bm[1], '·'));
                $bullets = array_values(array_filter(array_map('trim', explode('|', $bulletsRaw))));
            }

            // AUDIENCE: Wagner governança
            if (preg_match('/AUDIENCE:\s*(.+?)$/sui', $payload, $am)) {
                $audience = trim($am[1]) ?: null;
                if ($audience === 'qualquer' || $audience === '') {
                    $audience = null;
                }
            }
        }

        return [
            'tldr'     => $tldr ?: mb_substr($raw, 0, 280),
            'bullets'  => array_slice($bullets, 0, 6),
            'audience' => $audience,
        ];
    }

    protected function buildSuggestMetaPrompt(string $bodyText): string
    {
        return "RASCUNHO DE ARTIGO PRA AUTO-TAGGING:\n\n---\n{$bodyText}\n---\n\nResponda APENAS no formato:\n\nResposta: TITLE: <título ≤120 chars> · EXCERPT: <excerpt ≤280 chars> · TAGS: tag1, tag2, tag3 (3-8 tags) · CATEGORY: <slug ou 'incerto'> · NIVEL: iniciante|intermediario|avancado|n/a\nConfiança: alta|media|baixa";
    }

    /**
     * @return array{title:string, excerpt:string, tags:array<int,string>, category_slug:?string, nivel:?string}
     */
    protected function parseSuggestMetaResponse(string $raw, string $fallbackBody): array
    {
        $title = '';
        $excerpt = '';
        $tags = [];
        $category = null;
        $nivel = null;

        if (preg_match('/Resposta:\s*(.*?)(?:\n\s*Confian[çc]a:|$)/su', $raw, $m)) {
            $payload = trim($m[1]);

            if (preg_match('/TITLE:\s*(.*?)(?:·\s*EXCERPT:|EXCERPT:)/su', $payload, $tm)) {
                $title = trim(rtrim($tm[1], '·'));
            }
            if (preg_match('/EXCERPT:\s*(.*?)(?:·\s*TAGS:|TAGS:)/su', $payload, $em)) {
                $excerpt = trim(rtrim($em[1], '·'));
            }
            if (preg_match('/TAGS:\s*(.*?)(?:·\s*CATEGORY:|CATEGORY:)/su', $payload, $gm)) {
                $tagsRaw = trim(rtrim($gm[1], '·'));
                $tags = array_values(array_filter(array_map(
                    fn ($t) => mb_strtolower(trim($t)),
                    preg_split('/[,;]/', $tagsRaw) ?: []
                )));
            }
            if (preg_match('/CATEGORY:\s*(.*?)(?:·\s*NIVEL:|NIVEL:)/su', $payload, $cm)) {
                $cv = trim(rtrim($cm[1], '·'));
                $category = ($cv !== '' && mb_strtolower($cv) !== 'incerto') ? Str::slug($cv) : null;
            }
            if (preg_match('/NIVEL:\s*(.+?)$/sui', $payload, $nm)) {
                $nv = mb_strtolower(trim($nm[1]));
                if (in_array($nv, ['iniciante', 'intermediario', 'avancado'], true)) {
                    $nivel = $nv;
                }
            }
        }

        return [
            'title'         => mb_substr($title ?: '', 0, 120),
            'excerpt'       => mb_substr($excerpt ?: mb_substr($fallbackBody, 0, 280), 0, 300),
            'tags'          => array_slice(array_values(array_unique($tags)), 0, 8),
            'category_slug' => $category,
            'nivel'         => $nivel,
        ];
    }

    /**
     * Reuso interno de serializeBlocks (KbCorpusBuilder é protected).
     *
     * @param  array<int,array<string,mixed>>  $blocks
     */
    protected function serializeBlocksInline(array $blocks): string
    {
        $out = [];
        foreach ($blocks as $b) {
            if (! is_array($b)) {
                continue;
            }
            $kind = $b['kind'] ?? 'para';

            switch ($kind) {
                case 'h2':
                case 'h3':
                case 'para':
                case 'callout':
                    $out[] = (string) ($b['text'] ?? '');
                    break;
                case 'list':
                    $items = $b['items'] ?? [];
                    if (is_array($items)) {
                        $out[] = implode(' · ', array_map('strval', $items));
                    }
                    break;
                case 'image':
                    $alt = (string) ($b['alt'] ?? '');
                    if ($alt !== '') {
                        $out[] = "[imagem: {$alt}]";
                    }
                    break;
                default:
                    $out[] = (string) ($b['text'] ?? '');
            }
        }

        return trim(implode("\n", array_filter($out)));
    }

    protected function askCacheKey(string $query, int $businessId, string $corpusHash, array $opts): string
    {
        $idem = $opts['idempotency_key'] ?? null;
        if ($idem !== null) {
            return 'kb:ai:ask:idem:' . sha1((string) $idem) . ":biz:{$businessId}";
        }

        return 'kb:ai:ask:' . sha1($query . '|' . $corpusHash) . ":biz:{$businessId}";
    }

    protected function summarizeCacheKey(string $slug, int $businessId, KbNode $node): string
    {
        $sourceUpdated = $node->sourceDoc?->updated_at?->timestamp ?? 0;
        $nodeUpdated   = $node->updated_at?->timestamp ?? 0;
        $key = "kb:ai:sum:biz:{$businessId}:{$slug}:" . $nodeUpdated . ':' . $sourceUpdated;

        return $key;
    }

    protected function estimateCostBrl(int $tokensIn, int $tokensOut): float
    {
        $usdToBrl = (float) config('kb.usd_to_brl', self::USD_TO_BRL_FALLBACK);

        $costInUsd  = ($tokensIn  / 1_000_000) * self::PRICE_INPUT_PER_M_USD;
        $costOutUsd = ($tokensOut / 1_000_000) * self::PRICE_OUTPUT_PER_M_USD;

        return round(($costInUsd + $costOutUsd) * $usdToBrl, 6);
    }

    protected function latencyMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    protected function assertBusinessId(int $businessId): void
    {
        if ($businessId <= 0) {
            throw new \InvalidArgumentException(
                'KbRagService exige business_id positivo explícito (Tier 0 — ADR 0093).'
            );
        }
    }

    /**
     * Sanitização PII canônica (LGPD — Modules/Jana/Services/Privacy/PiiRedactor).
     *
     * Cobre CPF, CNPJ, email, CEP, phone BR. Tudo que vai pra LLM externo + cache + log
     * passa aqui (defense in depth — Wagner regra ADR 0094 + COPI-43).
     */
    protected function redactPii(string $text): string
    {
        try {
            /** @var PiiRedactor $redactor */
            $redactor = app(PiiRedactor::class);
            return $redactor->redact($text);
        } catch (\Throwable $e) {
            // Fallback regex inline mínimo se PiiRedactor indisponível
            $text = preg_replace('/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/', '[CPF_REDACTED]', $text) ?? $text;
            $text = preg_replace('/\b\d{2}\.?\d{3}\.?\d{3}\/?0001-?\d{2}\b/', '[CNPJ_REDACTED]', $text) ?? $text;
            return $text;
        }
    }
}
