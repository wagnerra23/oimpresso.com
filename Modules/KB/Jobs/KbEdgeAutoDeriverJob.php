<?php

declare(strict_types=1);

namespace Modules\KB\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\KB\Entities\KbNode;
use Modules\KB\Services\KbEdgeAutoDeriver;

/**
 * KbEdgeAutoDeriverJob — re-deriva edges auto pra todos os nodes de um business.
 *
 * Diferente de KbBridgeFromMcpJob (que sincroniza mcp_memory_documents → kb_nodes
 * E deriva edges), este job RE-DERIVA edges sem tocar nodes — útil quando:
 *   - tipos novos de edge foram adicionados ao KbEdgeAutoDeriver
 *   - tags de um node mudaram (related-by-tag precisa recalcular)
 *   - admin pediu "re-indexar grafo"
 *
 * **Multi-tenant Tier 0 (ADR 0093):** $businessId no constructor.
 *
 * Para nodes do tipo bridge (is_editable=false), busca McpMemoryDocument relacionado
 * pra alimentar o deriver com content_md/frontmatter.
 *
 * Para nodes editáveis (artigos), só roda related-by-tag (não tem doc fonte).
 */
class KbEdgeAutoDeriverJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function backoff(): array
    {
        return [120, 600];
    }

    public function __construct(public readonly int $businessId)
    {
    }

    public function handle(KbEdgeAutoDeriver $deriver): void
    {
        $count = 0;

        // SUPERADMIN: job em fila roda sem sessão — business_id explícito abaixo (vem do construtor)
        KbNode::withoutGlobalScopes()
            ->where('business_id', $this->businessId)
            ->whereNull('deleted_at')
            ->with('sourceDoc')
            ->chunk(200, function ($chunk) use ($deriver, &$count) {
                foreach ($chunk as $node) {
                    $doc = $node->sourceDoc;

                    try {
                        if ($doc) {
                            $count += $deriver->deriveSupersedes($node, $doc);
                            $count += $deriver->deriveCharterOf($node, $doc);
                            $count += $deriver->deriveCrossLink($node, $doc);
                            $count += $deriver->deriveRelated($node, $doc);
                        } else {
                            // Artigo editável — sintetiza pseudo-doc pra related-by-tag.
                            // TODO[CL]: refatorar KbEdgeAutoDeriver pra aceitar tags array em vez de obj
                            // pra evitar este hack.
                            $pseudoDoc = new \Modules\Jana\Entities\Mcp\McpMemoryDocument([
                                'business_id' => $node->business_id,
                                'tags'        => $node->tags,
                            ]);
                            $pseudoDoc->id = null; // não persistir
                            $count += $deriver->deriveRelated($node, $pseudoDoc);
                        }
                    } catch (\Throwable $e) {
                        Log::warning('KbEdgeAutoDeriverJob: node falhou', [
                            'business_id' => $this->businessId,
                            'node_id'     => $node->id,
                            'error'       => $e->getMessage(),
                        ]);
                    }
                }
            });

        Log::info('KbEdgeAutoDeriverJob: completo', [
            'business_id'    => $this->businessId,
            'edges_derived'  => $count,
        ]);
    }

    public function tags(): array
    {
        return ['kb', 'edge-derive', "business:{$this->businessId}"];
    }
}
