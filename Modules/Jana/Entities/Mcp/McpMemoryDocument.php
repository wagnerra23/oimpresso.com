<?php

namespace Modules\Jana\Entities\Mcp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Scout\Searchable;

/**
 * MEM-MCP-1.a (ADR 0053) — Documento da memory/ cacheado em DB.
 *
 * Cache governado de memory/decisions/, memory/sessions/, memory/handoff,
 * CURRENT.md, TASKS.md. MCP server (Proxmox) só lê desta tabela —
 * NUNCA acessa filesystem direto.
 *
 * Sync via job IndexarMemoryGitParaDb (webhook GitHub OU cron 5min).
 *
 * REPO-WIDE: ADR 0053 docs canon do git são da plataforma, não per-business.
 * `business_id` nullable preserva back-compat (legado pré-MEM-MULTI-1) mas
 * default é NULL (compartilhado). Wave 25 SATURATION marker explícito pra
 * rubrica D1.c v3.2 hardened.
 */
class McpMemoryDocument extends Model
{
    use SoftDeletes, Searchable;

    protected $table = 'mcp_memory_documents';

    protected $fillable = [
        'business_id', 'slug', 'type', 'module', 'title', 'content_md',
        'scope_required', 'admin_only', 'metadata',
        'git_sha', 'git_path', 'pii_redactions_count',
        'embedding', 'indexed_at',
        // MEM-KB-3 / F1 — colunas tipadas do frontmatter
        'status', 'authority', 'lifecycle', 'quarter', 'decided_at',
        'decided_by', 'tags', 'supersedes', 'superseded_by', 'related',
        'has_pii',
        // GAP D3 #1 — Contextual Retrieval Anthropic
        'contextual_context', 'contextual_indexed', 'contextualized_at',
    ];

    protected $casts = [
        'metadata'             => 'array',
        'admin_only'           => 'boolean',
        'pii_redactions_count' => 'integer',
        'indexed_at'           => 'datetime',
        // MEM-KB-3 / F1
        'decided_at'    => 'date',
        'decided_by'    => 'array',
        'tags'          => 'array',
        'supersedes'    => 'array',
        'superseded_by' => 'array',
        'related'       => 'array',
        'has_pii'       => 'boolean',
        // GAP D3 #1
        'contextual_indexed' => 'boolean',
        'contextualized_at'  => 'datetime',
    ];

    protected $hidden = ['embedding']; // BLOB, não enviar nas APIs

    public function history(): HasMany
    {
        return $this->hasMany(McpMemoryDocumentHistory::class, 'document_id');
    }

    /**
     * Filtra por empresa dona do documento (multi-tenant).
     * NULL = global (legado pré-MEM-MULTI-1 ou docs compartilhados).
     */
    public function scopeDoBusiness($query, int $businessId)
    {
        return $query->where(function ($q) use ($businessId) {
            $q->where('business_id', $businessId)
              ->orWhereNull('business_id');
        });
    }

    public function scopeAcessiveisPara($query, ?\App\User $user)
    {
        if ($user === null) {
            // Sem auth, só docs públicas
            return $query->whereNull('scope_required')->where('admin_only', false);
        }

        // Admin (superadmin) vê tudo
        if (method_exists($user, 'hasRole') && $user->hasRole('superadmin')) {
            return $query;
        }

        // Demais: filtra por scope_required vs Spatie permissions do user
        return $query->where(function ($q) use ($user) {
            $q->whereNull('scope_required')
              ->orWhereIn('scope_required', $user->getAllPermissions()->pluck('name'));
        })->where('admin_only', false);
    }

    public function scopeDoTipo($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeDoModulo($query, string $module)
    {
        return $query->where('module', $module);
    }

    /**
     * Full-text search nativo MySQL via FULLTEXT index.
     */
    public function scopeBuscarTexto($query, string $termo)
    {
        if (trim($termo) === '') {
            return $query;
        }
        return $query->whereRaw(
            'MATCH(title, content_md) AGAINST(? IN NATURAL LANGUAGE MODE)',
            [$termo]
        );
    }

    /**
     * Busca HYBRID (semantic + full-text) no índice Meilisearch — para as tools MCP
     * decisions-search/kb-answer recuperarem com a qualidade do pipeline do chat.
     *
     * Multi-tenant Tier 0 (ADR 0093): aplica `doBusiness($businessId)` na hidratação
     * — SIMÉTRICO ao caminho FULLTEXT (DecisionsSearch/KbAnswer chamam doBusiness quando
     * businessId>0). doBusiness = `business_id = X OR business_id IS NULL`, então docs de
     * plataforma (NULL = ADR 0053 cross-tenant by design) aparecem pra todos, mas docs
     * de um business específico NÃO vazam pra outro tenant. (Revisão adversarial 2026-05-29
     * pegou a assimetria: hybrid não filtrava enquanto FULLTEXT filtrava.)
     * + acessiveisPara (permissão Spatie). Embedder/ratio do config (qwen3_local / 0.6).
     * `shouldBeSearchable` já exclui superseded/deprecated do índice.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, static>
     */
    public static function buscarHybrid(string $query, int $limit, ?\App\User $user, ?string $tipo = null, ?string $module = null, int $businessId = 0): \Illuminate\Database\Eloquent\Collection
    {
        // Embedder do índice mcp_memory_documents (verificado live CT 100: nomic_local +
        // qwen3_local). NÃO usar copiloto.memoria.meilisearch.embedder — essa é a do chat
        // (jana_memoria_facts), que no prod resolve 'openai' e NÃO existe neste índice.
        $embedder = (string) config('copiloto.mcp_search.docs_embedder', 'qwen3_local');
        $ratio    = (float) config('copiloto.memoria.meilisearch.semantic_ratio', 0.6);

        // `recusado` é terminal-mas-VISÍVEL (anti-relitígio) — simétrico ao scopePorStatusAtivo do
        // caminho FULLTEXT. Sem isto, com docs_pipeline=true o recusado some da busca (índice tem,
        // mas a query corta). shouldBeSearchable já NÃO exclui recusado do índice.
        $filtros = ["status IN ['aceito','accepted','accepted-historical','recusado']"];
        if ($tipo !== null && $tipo !== '' && $tipo !== 'all') {
            $filtros[] = "type = '".addslashes($tipo)."'";
        }
        if ($module !== null && $module !== '') {
            $filtros[] = "module = '".addslashes($module)."'";
        }
        $filtro = implode(' AND ', $filtros);

        // HTTP direto na API REST do Meilisearch em vez de Scout::search() (US-RET-003,
        // verificado live no CT 100 em 2026-07-04). Motivo: o SCOUT_DRIVER do ambiente
        // resolve pro default 'collection' (config/scout.php:19), cujo engine IGNORA o
        // parâmetro `hybrid` e faz LIKE no banco — o retrieval semântico do índice (embedder
        // qwen3_local, custo zero) NUNCA era exercido. Resultado: kb-answer/decisions-search
        // caíam em recall lexical (recall@5 = 0.074 no golden set de 27 queries). Chamando a
        // API REST diretamente com o mesmo embedder, recall@5 sobe pra 0.704 (~9.5x), sem
        // depender do driver do Scout. A hidratação Eloquent abaixo REAPLICA os scopes Tier 0
        // (acessiveisPara + doBusiness), então o isolamento multi-tenant é idêntico ao FULLTEXT.
        $host  = rtrim((string) config('scout.meilisearch.host', ''), '/');
        $key   = (string) config('scout.meilisearch.key', '');
        $index = 'mcp_memory_documents'; // searchableAs() — constante deste índice

        // Collection vazia (tipo static, sem carregar linhas) — dispara o fallback FULLTEXT no caller.
        if ($host === '') {
            return static::query()->whereRaw('1 = 0')->get();
        }

        // ADR 0322 — o qwen3-embedding é instruction-aware (assimétrico): a query precisa
        // do prefixo `Instruct: …\nQuery: …` senão a similaridade INVERTE (causa-raiz
        // medida da ADR 0312). O embedding da query prefixada é pré-computado no Ollama e
        // enviado como `vector`; o `q` continua raw pro lado lexical (BM25) não ser poluído
        // pelo prefixo. Ollama indisponível → degrada pro hybrid raw (nunca quebra a busca).
        $body = [
            'q'                    => $query,
            'limit'                => $limit,
            'filter'               => $filtro,
            'attributesToRetrieve' => ['id'],
            'hybrid'               => ['embedder' => $embedder, 'semanticRatio' => $ratio],
        ];
        $vector = self::embedQueryComInstrucao($query, $embedder);
        if ($vector !== null) {
            $body['vector'] = $vector;
        }

        try {
            $resp = Http::withToken($key)->timeout(15)->post("{$host}/indexes/{$index}/search", $body);
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->warning('buscarHybrid: Meilisearch inacessível, fallback FULLTEXT: '.$e->getMessage());

            return static::query()->whereRaw('1 = 0')->get();
        }

        if ($resp->failed()) {
            Log::channel('copiloto-ai')->warning('buscarHybrid: Meilisearch HTTP '.$resp->status().' — fallback FULLTEXT');

            return static::query()->whereRaw('1 = 0')->get();
        }

        $ids = array_values(array_filter(array_map(
            static fn ($hit) => $hit['id'] ?? null,
            (array) $resp->json('hits', [])
        )));
        if ($ids === []) {
            return static::query()->whereRaw('1 = 0')->get();
        }

        // Hidrata reaplicando os scopes Tier 0 (idênticos ao FULLTEXT) e reordena pela
        // relevância do Meilisearch (whereIn não preserva a ordem dos ids).
        $pos = array_flip($ids);

        return static::query()
            ->whereIn('id', $ids)
            ->acessiveisPara($user)
            ->when($businessId > 0, static fn ($q) => $q->doBusiness($businessId)) // Tier 0 — ADR 0093
            ->get()
            ->sortBy(static fn ($doc) => $pos[(int) $doc->getKey()] ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * Embedding da query COM instrução (ADR 0322) — direto no Ollama, mesmo modelo do
     * índice (config-as-code `copiloto.meilisearch_indexes.mcp_memory_documents`).
     *
     * null = sem prefixo (instrução vazia, config ausente, ou Ollama fora) → o caller
     * manda o `q` raw pro Meilisearch embeddar (comportamento pré-0322). Fail-open de
     * qualidade, nunca de disponibilidade.
     *
     * @return array<int, float>|null
     */
    private static function embedQueryComInstrucao(string $query, string $embedder): ?array
    {
        $instrucao = (string) config('copiloto.mcp_search.docs_query_instruction', '');
        if (trim($instrucao) === '') {
            return null;
        }

        $cfg   = (array) config("copiloto.meilisearch_indexes.mcp_memory_documents.embedders.{$embedder}", []);
        $url   = (string) ($cfg['url'] ?? '');
        $model = (string) ($cfg['model'] ?? '');
        if ($url === '' || $model === '') {
            return null;
        }

        try {
            $resp = Http::timeout(10)->post($url, ['model' => $model, 'prompt' => $instrucao.$query]);
            $vec  = $resp->successful() ? $resp->json('embedding') : null;

            if (! is_array($vec) || $vec === []) {
                Log::channel('copiloto-ai')->warning('buscarHybrid: embed com instrução sem vetor (HTTP '.$resp->status().') — segue q raw');

                return null;
            }

            return $vec;
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->warning('buscarHybrid: Ollama indisponível pro embed com instrução — segue q raw: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Filtra documentos por status no metadata (triagem 2026-05-06).
     *
     * Status canônicos:
     * - 'aceito' / 'accepted' / 'accepted-historical' → ativo (default)
     * - 'superseded' / 'substituido' / 'deprecated' / 'sunsetting' → arquivado
     *
     * Sem filtro: retorna ambos (use com cuidado em listagens públicas).
     *
     * @param  bool  $includeArchived  Se true, ignora filtro e retorna tudo.
     */
    public function scopePorStatusAtivo($query, bool $includeArchived = false)
    {
        if ($includeArchived) {
            return $query;
        }
        // metadata é JSON; usa JSON_EXTRACT pra ler $.status
        // Tolera ausência de metadata (status NULL = legado pré-triagem, considera ativo)
        return $query->where(function ($q) {
            $q->whereNull('metadata')
              ->orWhereRaw("JSON_EXTRACT(metadata, '$.status') IS NULL")
              ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.status')) IN (?, ?, ?, ?)", [
                  'aceito',
                  'accepted',
                  'accepted-historical',
                  // o NÃO é terminal-mas-VISÍVEL: `recusado` existe pra ser ACHADO (anti-relitígio),
                  // senão a busca esconde a decisão que evita re-propor. Proposal recusado-com-motivo.
                  'recusado',
              ]);
        });
    }

    // -------------------------------------------------------------------------
    // Scout / Meilisearch hybrid (Sprint 9 — ADR 0068)
    // Embedder: Ollama nomic-embed-text (local, custo zero).
    // MEILI_EXPERIMENTAL_ALLOWED_IP_NETWORKS no compose permite CIDR privado.
    // -------------------------------------------------------------------------

    public function searchableAs(): string
    {
        return 'mcp_memory_documents';
    }

    public function toSearchableArray(): array
    {
        // Remove frontmatter YAML (---\n...\n---\n) antes de gerar o excerpt.
        // Sem isso, ADRs com 200-400 chars de frontmatter geram vetores semanticamente
        // idênticos entre si e sem relação com o conteúdo real do documento.
        $body    = preg_replace('/^\s*---\n.*?\n---\n?/s', '', $this->content_md ?? '');
        $excerpt = mb_substr(trim($body), 0, 400);

        // GAP D3 #1 — Contextual Retrieval Anthropic.
        // Quando contextual_context populado, PREPENDA ao content_md indexado
        // pra que embedder/BM25 vejam contexto descritivo do doc ANTES do raw
        // (Anthropic blog 2024-09-19: -49% failed retrievals).
        // Doc sem contextualização (legado) cai no caminho normal sem regressão.
        $contextualContext = trim((string) ($this->contextual_context ?? ''));
        $contentIndexed = $contextualContext !== ''
            ? $contextualContext."\n\n".(string) $this->content_md
            : (string) $this->content_md;

        return [
            'id'              => $this->id,
            'slug'            => $this->slug,
            'title'           => $this->title,
            'content_md'      => $contentIndexed,
            'content_excerpt' => $excerpt,
            'type'            => $this->type,
            'module'          => $this->module,
            'status'          => $this->status ?? 'aceito',
            'tags'            => $this->tags ?? [],
            'has_contextual'  => $contextualContext !== '',
        ];
    }

    /**
     * Exclui do índice Meilisearch ADRs que não são mais canônicas.
     * Assim hybrid search nunca retorna um doc superseded/deprecated.
     */
    public function shouldBeSearchable(): bool
    {
        return ! in_array($this->status, ['superseded', 'deprecated', 'rascunho'], true);
    }

    /**
     * Cria snapshot history e atualiza atributos. Usar dentro de transaction.
     */
    public function snapshotEAtualizar(array $atributos, ?int $userId = null, string $reason = 'webhook'): void
    {
        // Snapshot da versão atual antes de atualizar
        if ($this->exists && ! empty($this->content_md)) {
            McpMemoryDocumentHistory::create([
                'document_id'         => $this->id,
                'slug'                => $this->slug,
                'git_sha'             => $this->git_sha,
                'title'               => $this->title,
                // Opção (c) metadata-only (incidente 2026-06-26): NÃO guarda o
                // snapshot do conteúdo (era mediumtext ~14 KB/linha — a fonte do
                // bloat que estourou a cota e revogou a escrita do ERP). O history
                // mantém só o metadado da versão (slug/git_sha/title/changed_at/
                // reason); o conteúdo é recuperável pelo git (canônico — ADR 0061).
                // String vazia (não null) evita migration + risco de ordem deploy↔
                // schema na coluna NOT NULL. Nada lê history.content_md hoje.
                'content_md'          => '',
                'metadata'            => $this->metadata,
                'changed_at'          => now(),
                'changed_by_user_id'  => $userId,
                'change_reason'       => $reason,
            ]);

            // Camada 1 (defesa-em-profundidade vs incidente 2026-06-26): teto no
            // write — mantém só as últimas N versões deste doc. Bounda a tabela em
            // docs × N permanentemente, independente do cron de poda rodar a tempo.
            McpMemoryDocumentHistory::podarExcedentePorDoc((int) $this->id);
        }

        $this->update($atributos);
    }
}
