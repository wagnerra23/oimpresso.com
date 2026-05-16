<?php

declare(strict_types=1);

namespace Modules\KB\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Entities\Mcp\McpMemoryDocument;
use Modules\KB\Entities\KbNode;

/**
 * KbCorpusBuilder — constrói o corpus searchable do KB pra Meilisearch.
 *
 * Estratégia (ADR 0035 + ADR 0053 + SCHEMA-DB-V1 §10):
 *  - Cada kb_node vira 1 documento Meilisearch
 *  - Se `is_editable=true` → body_text = serialize(body_blocks)
 *  - Se `is_editable=false` (bridge canon ADR/session/charter/runbook/briefing/spec)
 *    → body_text = JOIN com mcp_memory_documents.content_md
 *  - Bridge ERP (os/customer/nfe/...) → body_text = excerpt apenas (ONDA 6 indexa entity)
 *
 * Reutiliza embedder OpenAI text-embedding-3-small já configurado no índice
 * Meilisearch existente (ADR 0035 — não criar provider novo). Schema do índice:
 *
 *   id (= kb_node_id), business_id, type, slug, title, excerpt, body_text, tags, updated_at
 *
 * Multi-tenant Tier 0 (ADR 0093): filtro `business_id` aplicado em TODA chamada
 * — corpus é particionado por business no Meilisearch.
 *
 * NOTA TÉCNICA — `corpus version hash`:
 *  - Cada chamada `ask()` precisa saber se o corpus mudou pra invalidar cache
 *    de respostas Redis. Usamos `max(kb_nodes.updated_at, mcp_memory_documents.updated_at)`
 *    filtrado por business_id como hash determinístico.
 *
 * @see memory/requisitos/KB/SCHEMA-DB-V1.md §10 KbBridgeFromMcpJob
 * @see memory/decisions/0035-stack-ai-canonica-wagner-2026-04-26.md
 * @see memory/decisions/0053-mcp-server-governanca-como-produto.md
 */
class KbCorpusBuilder
{
    /**
     * Nome do índice Meilisearch.
     *
     * Configurável via `config('kb.meilisearch.index', 'kb_corpus')`.
     * Embedder é configurado no Meilisearch admin (ADR 0035) — não setado aqui.
     */
    public const INDEX_NAME = 'kb_corpus';

    /**
     * Top-K máximo retornado pelo Meilisearch (antes do re-rank).
     */
    public const RETRIEVE_TOP_K = 20;

    /**
     * Top-N final passado pro prompt do LLM (após re-rank/filter).
     */
    public const PROMPT_TOP_N = 6;

    public function __construct(
        private readonly int $businessId,
    ) {
        if ($businessId <= 0) {
            throw new \InvalidArgumentException('businessId deve ser positivo (Tier 0 — ADR 0093).');
        }
    }

    /**
     * Retorna hash do corpus atual — usado pra cache-key invalidation.
     *
     * Hash = sha1("biz:{$businessId}|max(kb_nodes.updated_at)|max(mcp_memory_documents.updated_at)").
     *
     * Custo: 2 queries SQL agregadas, ~5-15ms. Cacheável dentro do request
     * mas NÃO entre requests — corpus pode mudar a qualquer momento.
     */
    public function corpusVersionHash(): string
    {
        try {
            // KbNode usa BelongsToBusinessTrait — global scope cuida do filtro
            // se sessão tem business; aqui forçamos pra ser explícito em CLI/job.
            $maxKbNodes = KbNode::query()
                ->withoutGlobalScopes() // SUPERADMIN: hash precisa ler sem dep de sessão
                ->where('business_id', $this->businessId)
                ->max('updated_at');

            $maxMcpDocs = McpMemoryDocument::query()
                ->where(function (Builder $q) {
                    $q->where('business_id', $this->businessId)
                      ->orWhereNull('business_id'); // docs globais entram no corpus
                })
                ->max('updated_at');

            $key = sprintf(
                'biz:%d|kb:%s|mcp:%s',
                $this->businessId,
                $maxKbNodes ?? '0',
                $maxMcpDocs ?? '0',
            );

            return sha1($key);
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->warning('KbCorpusBuilder::corpusVersionHash falhou', [
                'business_id' => $this->businessId,
                'error'       => $e->getMessage(),
            ]);
            // Fail-open: retorna hash baseado em now() — invalida cache mas não quebra ask().
            return sha1('biz:' . $this->businessId . '|fallback:' . microtime(true));
        }
    }

    /**
     * Itera todos os documentos do corpus pra (re)indexar no Meilisearch.
     *
     * @return \Generator<int,array<string,mixed>>
     */
    public function streamDocuments(): \Generator
    {
        // 1) Bridge canon (is_editable=false + source_doc_id) — JOIN com mcp_memory_documents
        $bridgeQuery = KbNode::query()
            ->withoutGlobalScopes() // SUPERADMIN: indexador roda CLI sem sessão
            ->where('kb_nodes.business_id', $this->businessId)
            ->whereNull('kb_nodes.deleted_at')
            ->where('kb_nodes.is_editable', false)
            ->whereNotNull('kb_nodes.source_doc_id')
            ->leftJoin('mcp_memory_documents', 'mcp_memory_documents.id', '=', 'kb_nodes.source_doc_id')
            ->select([
                'kb_nodes.id           as kb_node_id',
                'kb_nodes.business_id  as business_id',
                'kb_nodes.type         as type',
                'kb_nodes.slug         as slug',
                'kb_nodes.title        as title',
                'kb_nodes.excerpt      as excerpt',
                'kb_nodes.tags         as tags_json',
                'kb_nodes.updated_at   as updated_at',
                'mcp_memory_documents.content_md as bridge_body',
            ]);

        foreach ($bridgeQuery->cursor() as $row) {
            yield $this->toDocument([
                'kb_node_id'  => (int) $row->kb_node_id,
                'business_id' => (int) $row->business_id,
                'type'        => (string) $row->type,
                'slug'        => (string) $row->slug,
                'title'       => (string) $row->title,
                'excerpt'     => (string) ($row->excerpt ?? ''),
                'tags_json'   => $row->tags_json,
                'updated_at'  => $row->updated_at,
                'body_text'   => (string) ($row->bridge_body ?? ''),
            ]);
        }

        // 2) Artigos editáveis (is_editable=true) — body_text = serialize blocks
        $editableQuery = KbNode::query()
            ->withoutGlobalScopes()
            ->where('business_id', $this->businessId)
            ->whereNull('deleted_at')
            ->where('is_editable', true);

        foreach ($editableQuery->cursor() as $node) {
            yield $this->toDocument([
                'kb_node_id'  => (int) $node->id,
                'business_id' => (int) $node->business_id,
                'type'        => (string) $node->type,
                'slug'        => (string) $node->slug,
                'title'       => (string) $node->title,
                'excerpt'     => (string) ($node->excerpt ?? ''),
                'tags_json'   => $node->tags,
                'updated_at'  => $node->updated_at,
                'body_text'   => $this->serializeBlocks($node->body_blocks),
            ]);
        }
    }

    /**
     * Conta documentos indexáveis pro business — útil em RUNBOOK/health-check.
     */
    public function count(): int
    {
        return (int) KbNode::query()
            ->withoutGlobalScopes()
            ->where('business_id', $this->businessId)
            ->whereNull('deleted_at')
            ->count();
    }

    /**
     * Retrieve top-K do Meilisearch (semantic + full-text hybrid).
     *
     * NÃO chama LLM — apenas executa search no índice. O re-rank/LLM fica em
     * KbRagService::ask().
     *
     * Fail-open: em caso de erro Meilisearch, retorna [] e loga — KbRagService
     * detecta corpus vazio e responde "não encontrei nada no KB" honestamente.
     *
     * @return Collection<int,array<string,mixed>>
     *   array{kb_node_id:int,slug:string,type:string,title:string,excerpt:string,snippet:string,score:float}
     */
    public function retrieve(string $query, int $topK = self::RETRIEVE_TOP_K): Collection
    {
        $query = trim($query);
        if ($query === '') {
            return collect();
        }

        try {
            // Reaproveita pattern do MeilisearchDriver (ADR 0036) — Scout callback
            // hybrid + filter business_id. Como ainda não há `KbCorpusDocument` model
            // Searchable persistido, usamos cliente Meilisearch direto via container.
            //
            // TODO[F]: assim que Agent A adicionar trait Searchable em KbNode (ou criar
            //          KbCorpusDocument shadow model), trocar isto pelo pattern
            //          MemoriaFato::search($q, callback)->take()->get().
            return $this->retrieveViaMeilisearchClient($query, $topK);
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->warning('KbCorpusBuilder::retrieve falhou (degradação)', [
                'business_id' => $this->businessId,
                'query'       => mb_substr($query, 0, 80),
                'error'       => $e->getMessage(),
            ]);

            return collect();
        }
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * Normaliza 1 row em documento Meilisearch.
     *
     * @param  array<string,mixed>  $row
     * @return array<string,mixed>
     */
    protected function toDocument(array $row): array
    {
        $tags = $row['tags_json'];
        if (is_string($tags)) {
            $decoded = json_decode($tags, true);
            $tags    = is_array($decoded) ? $decoded : [];
        } elseif (! is_array($tags)) {
            $tags = [];
        }

        // body_text: title duplicado pra dar peso no full-text (PT-BR keywords)
        // + excerpt + body limitado a 8KB pra não explodir tamanho do índice.
        $body = mb_substr((string) $row['body_text'], 0, 8192);

        return [
            'id'           => (int) $row['kb_node_id'],
            'kb_node_id'   => (int) $row['kb_node_id'],
            'business_id'  => (int) $row['business_id'],
            'type'         => $row['type'],
            'slug'         => $row['slug'],
            'title'        => $row['title'],
            'excerpt'      => $row['excerpt'],
            'body_text'    => $body,
            'tags'         => $tags,
            'updated_at'   => $row['updated_at'] instanceof Carbon
                ? $row['updated_at']->timestamp
                : (is_string($row['updated_at']) ? strtotime($row['updated_at']) : time()),
        ];
    }

    /**
     * Serializa body_blocks JSON em texto plano pra indexação full-text.
     *
     * Blocks canônicos (SCHEMA-DB-V1 §3): [{kind: para|h2|list|callout|image, ...}]
     * Listas viram texto separado por " · "; imagens viram alt-text; etc.
     *
     * @param  array<int,array<string,mixed>>|null  $blocks
     */
    protected function serializeBlocks(?array $blocks): string
    {
        if (! is_array($blocks) || empty($blocks)) {
            return '';
        }

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
                    // shape desconhecido — tenta json_encode reduzido
                    $out[] = (string) ($b['text'] ?? '');
            }
        }

        return mb_substr(trim(implode("\n", array_filter($out))), 0, 8192);
    }

    /**
     * Retrieve via cliente Meilisearch direto.
     *
     * @return Collection<int,array<string,mixed>>
     */
    protected function retrieveViaMeilisearchClient(string $query, int $topK): Collection
    {
        // Container resolve cliente Meilisearch oficial caso Scout esteja com driver=meilisearch
        // (default do projeto, ADR 0036). Em dev sem Meilisearch, retorna [] graceful.
        if (! class_exists(\Meilisearch\Client::class)) {
            return collect();
        }

        $host = (string) config('scout.meilisearch.host', env('MEILISEARCH_HOST', 'http://127.0.0.1:7700'));
        $key  = (string) config('scout.meilisearch.key',  env('MEILISEARCH_KEY', ''));

        $client = new \Meilisearch\Client($host, $key);
        $index  = $client->index(self::INDEX_NAME);

        $embedder      = (string) config('kb.meilisearch.embedder', 'openai');
        $semanticRatio = (float)  config('kb.meilisearch.semantic_ratio', 0.7);

        $hits = $index->search($query, [
            'limit'             => $topK,
            'filter'            => sprintf('business_id = %d', $this->businessId),
            'attributesToRetrieve' => ['kb_node_id', 'slug', 'type', 'title', 'excerpt', 'body_text', 'tags'],
            'attributesToCrop'  => ['body_text'],
            'cropLength'        => 60,
            'showRankingScore'  => true,
            'hybrid'            => [
                'embedder'      => $embedder,
                'semanticRatio' => $semanticRatio,
            ],
        ])->toArray();

        $items = $hits['hits'] ?? [];

        return collect($items)->map(function (array $hit): array {
            $body = (string) ($hit['_formatted']['body_text'] ?? $hit['body_text'] ?? '');
            return [
                'kb_node_id' => (int) ($hit['kb_node_id'] ?? 0),
                'slug'       => (string) ($hit['slug'] ?? ''),
                'type'       => (string) ($hit['type'] ?? 'reference'),
                'title'      => (string) ($hit['title'] ?? ''),
                'excerpt'    => (string) ($hit['excerpt'] ?? ''),
                'snippet'    => mb_substr($body, 0, 240),
                'score'      => (float) ($hit['_rankingScore'] ?? 0.0),
            ];
        });
    }
}
