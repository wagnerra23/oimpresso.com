<?php

namespace Modules\Jana\Entities\Mcp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
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

        $filtros = ["status IN ['aceito','accepted','accepted-historical']"];
        if ($tipo !== null && $tipo !== '' && $tipo !== 'all') {
            $filtros[] = "type = '".addslashes($tipo)."'";
        }
        if ($module !== null && $module !== '') {
            $filtros[] = "module = '".addslashes($module)."'";
        }
        $filtro = implode(' AND ', $filtros);

        return static::search($query, function ($index, string $q, array $params) use ($embedder, $ratio, $filtro, $limit) {
            $params['hybrid'] = ['embedder' => $embedder, 'semanticRatio' => $ratio];
            $params['filter'] = $filtro;
            $params['limit']  = $limit;

            return $index->search($q, $params);
        })
            ->query(function ($q) use ($user, $businessId) {
                $q->acessiveisPara($user);
                if ($businessId > 0) {
                    $q->doBusiness($businessId); // Tier 0 — simétrico ao FULLTEXT (ADR 0093)
                }

                return $q;
            })
            ->take($limit)
            ->get();
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
                'content_md'          => $this->content_md,
                'metadata'            => $this->metadata,
                'changed_at'          => now(),
                'changed_by_user_id'  => $userId,
                'change_reason'       => $reason,
            ]);
        }

        $this->update($atributos);
    }
}
