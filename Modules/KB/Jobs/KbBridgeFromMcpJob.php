<?php

declare(strict_types=1);

namespace Modules\KB\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Jana\Entities\Mcp\McpMemoryDocument;
use Modules\KB\Entities\KbNode;
use Modules\KB\Services\KbBridgeStateService;
use Modules\KB\Services\KbEdgeAutoDeriver;

/**
 * KbBridgeFromMcpJob — sincroniza mcp_memory_documents → kb_nodes (read-only bridge).
 *
 * Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md §10
 *
 * **Multi-tenant Tier 0 (ADR 0093):** $businessId no constructor — NUNCA session().
 *
 * Pseudocódigo:
 *   foreach McpMemoryDocument do business, updated_at > last_bridge_at:
 *     - firstOrNew em kb_nodes WHERE source_doc_id = doc.id
 *     - fill com type/slug/title/excerpt/tags do doc
 *     - is_editable=false, body_blocks=NULL  ← INVARIANT
 *     - status = doc.deleted_at ? 'deleted' : 'ok'
 *     - save (KbNodeObserver valida invariant)
 *     - KbEdgeAutoDeriver: supersedes, related-by-tag, charter-of, cross-link
 *
 * Tipos de doc que viram kb_nodes (mapping):
 *   doc.type = 'adr'       → kb_nodes.type = 'adr'
 *   doc.type = 'session'   → kb_nodes.type = 'session'
 *   doc.type = 'charter'   → kb_nodes.type = 'charter'
 *   doc.type = 'runbook'   → kb_nodes.type = 'runbook'
 *   doc.type = 'briefing'  → kb_nodes.type = 'briefing'
 *   doc.type = 'spec'      → kb_nodes.type = 'spec'
 *   doc.type = 'comparativo' → kb_nodes.type = 'comparativo'
 *   doc.type = 'reference' → kb_nodes.type = 'reference'
 *   (outros)               → pula (não-bridge no V1)
 *
 * **ADR canon append-only:** este job NUNCA cria kb_node_versions pra bridges
 * (Observer já bloqueia, mas reforço: bridges não chamam updating com is_editable=true).
 *
 * Retries: 3 com backoff exponencial. Falha registrada em kb_bridge_state.last_error.
 *
 * Schedule: every 15 min em prod via app/Console/Kernel.php.
 *
 * @see memory/decisions/0149-kb-unificado-grafo-conhecimento-modulo-ia-central.md
 */
class KbBridgeFromMcpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    /**
     * @param  int  $businessId  business a sincronizar (NUNCA usar session() em queue)
     * @param  bool $forceFullSweep  se true, ignora last_bridge_at e re-bridgeia tudo
     */
    public function __construct(
        public readonly int $businessId,
        public readonly bool $forceFullSweep = false,
    ) {
    }

    public function handle(
        KbBridgeStateService $stateService,
        KbEdgeAutoDeriver $edgeDeriver,
    ): void {
        $docsProcessed = 0;
        $edgesDerived = 0;
        $error = null;

        try {
            $lastBridgeAt = $this->forceFullSweep ? null : $stateService->getLastBridgeAt($this->businessId);

            $query = McpMemoryDocument::query()
                ->withTrashed() // bridge captura deleted (status='deleted') também
                ->whereIn('type', $this->bridgeableTypes())
                ->where(function ($q) {
                    $q->where('business_id', $this->businessId)
                      ->orWhereNull('business_id'); // docs globais (ADR canon vê do biz)
                });

            if ($lastBridgeAt !== null) {
                $query->where('updated_at', '>', $lastBridgeAt);
            }

            $query->orderBy('updated_at')->chunk(100, function ($chunk) use (&$docsProcessed, &$edgesDerived, $edgeDeriver) {
                foreach ($chunk as $doc) {
                    try {
                        $node = $this->bridgeDocument($doc);
                        $docsProcessed++;

                        // Auto-derive edges. Cada deriver é idempotente (UNIQUE).
                        $edgesDerived += $edgeDeriver->deriveSupersedes($node, $doc);
                        $edgesDerived += $edgeDeriver->deriveRelated($node, $doc);
                        $edgesDerived += $edgeDeriver->deriveCharterOf($node, $doc);
                        $edgesDerived += $edgeDeriver->deriveCrossLink($node, $doc);
                    } catch (\Throwable $e) {
                        // PII redaction — slug pode conter dados sensíveis em raras situações.
                        // TODO[CL]: usar App\Services\PiiRedactor quando existir (Grep negativo 2026-05-15).
                        Log::warning('KbBridgeFromMcpJob: doc falhou', [
                            'business_id' => $this->businessId,
                            'doc_id'      => $doc->id,
                            'slug'        => substr((string) $doc->slug, 0, 80),
                            'error'       => $e->getMessage(),
                        ]);
                    }
                }
            });

            $stateService->markRun($this->businessId, $docsProcessed, $edgesDerived, null);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            $stateService->markRun($this->businessId, $docsProcessed, $edgesDerived, $error);

            Log::error('KbBridgeFromMcpJob: falha geral', [
                'business_id' => $this->businessId,
                'docs_processed' => $docsProcessed,
                'error' => $error,
            ]);

            throw $e; // re-throw pra Horizon registrar como failed e re-tentar
        }
    }

    /**
     * Cria/atualiza um KbNode pra refletir o McpMemoryDocument.
     *
     * INVARIANTE preservada: body_blocks=NULL + is_editable=false.
     */
    private function bridgeDocument(McpMemoryDocument $doc): KbNode
    {
        // Resolução determinística do business_id no node:
        //  - se doc.business_id != null, usa esse
        //  - senão, usa o business do job (docs globais nascem em cada biz)
        $bizForNode = $doc->business_id ?? $this->businessId;

        // SUPERADMIN: job em fila bridge MCP→KB roda sem sessão — business_id ($bizForNode) explícito na chave
        $node = KbNode::withoutGlobalScopes()
            ->firstOrNew([
                'business_id'   => $bizForNode,
                'source_doc_id' => $doc->id,
            ]);

        // Excerpt: strip frontmatter + limita 400 chars (mesma heurística que toSearchableArray).
        $bodyForExcerpt = preg_replace('/^\s*---\n.*?\n---\n?/s', '', (string) $doc->content_md);
        $excerpt = Str::limit(trim(strip_tags((string) $bodyForExcerpt)), 400);

        $node->fill([
            'business_id'   => $bizForNode,
            'type'          => (string) $doc->type,
            'slug'          => (string) $doc->slug,
            'title'         => (string) $doc->title,
            'excerpt'       => $excerpt,
            'body_blocks'   => null, // ← INVARIANT Tier 0 — bridge canon não tem blocks
            'is_editable'   => false,
            'status'        => $doc->trashed() ? 'deleted' : 'ok',
            'tags'          => is_array($doc->tags) ? $doc->tags : null,
        ]);

        $node->save(); // KbNodeObserver enforce invariant

        return $node;
    }

    /**
     * Tipos de mcp_memory_documents que viram kb_nodes bridge.
     *
     * Tipos do MCP fora deste set (ex: 'feedback', 'auto-mem-historical') ficam
     * acessíveis em /copiloto/admin/memoria mas não aparecem no grafo KB V1.
     */
    private function bridgeableTypes(): array
    {
        return [
            'adr', 'session', 'charter', 'runbook',
            'briefing', 'spec', 'comparativo', 'reference',
        ];
    }

    public function tags(): array
    {
        return ['kb', 'bridge', "business:{$this->businessId}"];
    }
}
