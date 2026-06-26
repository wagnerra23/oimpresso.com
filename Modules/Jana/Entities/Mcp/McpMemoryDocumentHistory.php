<?php

namespace Modules\Jana\Entities\Mcp;

use App\Concerns\BelongsToBusinessViaParent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MEM-MCP-1.a (ADR 0053) — History append-only de mcp_memory_documents.
 *
 * Cada UPDATE no documento gera snapshot aqui. Tabela IMUTÁVEL.
 *
 * Multi-tenant Tier 0 (ADR 0093) — Wave 15: tenancy herdada via parent
 * `document` (mcp_memory_documents.business_id). Nota: parent McpMemoryDocument
 * tem tenancy híbrida (NULL = global) com scopes próprios — o scope via parent
 * aqui filtra apenas history cujo document.business_id casa session.
 */
class McpMemoryDocumentHistory extends Model
{
    use BelongsToBusinessViaParent;

    public $timestamps = false; // só created_at via default useCurrent()

    protected $table = 'mcp_memory_documents_history';

    /** Relação parent que carrega business_id (usada por ScopeByBusinessViaParent). */
    protected string $businessParentRelation = 'document';

    protected $fillable = [
        'document_id', 'slug', 'git_sha', 'title', 'content_md',
        'metadata', 'changed_at', 'changed_by_user_id', 'change_reason',
        'created_at',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'changed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(McpMemoryDocument::class, 'document_id');
    }

    /**
     * Teto duro de versões por documento (defesa-em-profundidade Camada 1 vs
     * incidente 2026-06-26). Fonte única do número — `jana:memory-history-prune`
     * (cron, Camada 0/B) e a poda no write (`snapshotEAtualizar`, Camada 1)
     * compartilham este teto.
     */
    public const KEEP_PER_DOC = 20;

    /**
     * TETO NO WRITE: mantém só as últimas KEEP_PER_DOC versões DESTE documento,
     * deletando o excedente na hora — idade ignorada. Torna o inchaço de
     * mcp_memory_documents_history IMPOSSÍVEL mesmo sob burst (não depende do cron
     * rodar a tempo). Reincidência 2026-06-26: a tabela voltou a 5,2 GB (374k
     * linhas em 4 dias de maratona de governança) e a Hostinger AUTO-REVOGOU
     * INSERT/UPDATE do ERP por estourar a cota — ROTA LIVRE parou de salvar.
     *
     * Escopo por document_id = um doc pertence a um único tenant, então a poda é
     * naturalmente isolada; usa withoutGlobalScopes pra não depender do contexto
     * de business do chamador (indexer roda por-business e docs globais têm
     * business_id NULL no parent).
     *
     * @return int linhas podadas
     */
    public static function podarExcedentePorDoc(int $documentId, ?int $keep = null): int
    {
        $keep = max(1, $keep ?? self::KEEP_PER_DOC);

        // SUPERADMIN: poda de cache de governança escopada por document_id
        // (1 doc = 1 tenant); independe do business da sessão (ADR 0093).
        $manter = self::withoutGlobalScopes()
            ->where('document_id', $documentId)
            ->orderByDesc('changed_at')
            ->orderByDesc('id')
            ->take($keep)
            ->pluck('id');

        if ($manter->count() < $keep) {
            return 0; // ainda dentro do teto — nada a podar
        }

        // SUPERADMIN: idem — deleta tudo do doc fora das KEEP mais novas.
        return self::withoutGlobalScopes()
            ->where('document_id', $documentId)
            ->whereNotIn('id', $manter->all())
            ->delete();
    }
}
