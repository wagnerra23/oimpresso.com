<?php

declare(strict_types=1);

namespace Modules\KB\Services;

use Illuminate\Support\Facades\Log;
use Modules\Jana\Entities\Mcp\McpMemoryDocument;
use Modules\KB\Entities\KbEdge;
use Modules\KB\Entities\KbNode;

/**
 * KbEdgeAutoDeriver — auto-deriva arestas tipadas a partir do frontmatter
 * ou conteúdo de um McpMemoryDocument (canônico) que está sendo bridge.
 *
 * Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md §4
 *
 * Tipos de aresta auto-deriváveis nesta classe:
 *   - supersedes        ← frontmatter `supersedes:`
 *   - charter-of        ← frontmatter `charter_adr:` OU path *.charter.md
 *   - related-by-tag    ← overlap de tags + heurística
 *   - cross-link        ← regex `#kb-NNN` em content_md
 *
 * Tipos NÃO cobertos aqui (vivem em ondas futuras):
 *   - references-data   → KbScanReferencesJob (ONDA 6)
 *   - ai-related        → KbAiRelateJob (ONDA 4)
 *
 * Idempotente: UNIQUE (business_id, from, to, edge_type) impede dupe.
 * Usa updateOrCreate.
 */
class KbEdgeAutoDeriver
{
    /**
     * Auto-deriva edges de supersedes a partir de `supersedes: [NNNN]` do frontmatter ADR.
     *
     * @return int número de edges criadas/atualizadas
     */
    public function deriveSupersedes(KbNode $node, McpMemoryDocument $doc): int
    {
        if ($node->type !== 'adr') {
            return 0;
        }

        $supersededSlugs = (array) ($doc->supersedes ?? []);
        if (empty($supersededSlugs)) {
            return 0;
        }

        $created = 0;
        foreach ($supersededSlugs as $slug) {
            $slug = trim((string) $slug);
            if ($slug === '') {
                continue;
            }

            // Resolve o slug pra um kb_node já bridgeado.
            $targetNode = KbNode::withoutGlobalScopes()
                ->where('business_id', $node->business_id)
                ->where('slug', $slug)
                ->first();

            if (! $targetNode) {
                // Target ainda não foi bridgeado — pula. Próxima run do bridge job pega.
                continue;
            }

            $this->upsertEdge(
                $node->business_id,
                $node->id,
                $targetNode->id,
                'supersedes',
                weight: 1.000,
                generatedBy: 'bridge_job',
                payload: ['source' => 'frontmatter:supersedes'],
            );
            $created++;
        }

        return $created;
    }

    /**
     * Auto-deriva edges related-by-tag por overlap de tags entre nodes do mesmo business.
     *
     * Heurística simples: cada par com >= 2 tags em comum gera 1 edge bidirecional
     * (na verdade, criamos só do nó atual pros outros — bidirecional fica como
     * 2 rows separadas pra eficiência de query).
     *
     * @return int número de edges criadas
     */
    public function deriveRelated(KbNode $node, McpMemoryDocument $doc): int
    {
        $tags = array_map('strval', (array) ($doc->tags ?? $node->tags ?? []));
        if (count($tags) < 2) {
            return 0;
        }

        // Encontra outros nodes do mesmo business com 2+ tags em comum.
        // SQL: lista nodes que tem JSON_CONTAINS pra qualquer das tags.
        $candidates = KbNode::withoutGlobalScopes()
            ->where('business_id', $node->business_id)
            ->where('id', '<>', $node->id)
            ->whereNotNull('tags')
            ->limit(50) // safety cap
            ->get();

        $created = 0;
        foreach ($candidates as $candidate) {
            $candidateTags = array_map('strval', (array) $candidate->tags);
            $overlap = array_intersect($tags, $candidateTags);
            if (count($overlap) < 2) {
                continue;
            }

            $score = round(count($overlap) / max(count($tags), count($candidateTags)), 3);

            $this->upsertEdge(
                $node->business_id,
                $node->id,
                $candidate->id,
                'related-by-tag',
                weight: (float) $score,
                generatedBy: 'tag_overlap',
                payload: ['tags_in_common' => array_values($overlap)],
            );
            $created++;
        }

        return $created;
    }

    /**
     * Auto-deriva edge charter-of:
     *   - frontmatter `charter_adr: NNNN` aponta pra ADR
     *   - path *.charter.md sugere page React relacionada
     *
     * V1: só lê frontmatter `charter_adr`. Detecção de Page React fica V2 (precisa
     * mapping path → entity, complexo).
     *
     * @return int número de edges criadas
     */
    public function deriveCharterOf(KbNode $node, McpMemoryDocument $doc): int
    {
        $charterAdr = $doc->metadata['charter_adr'] ?? null;
        if ($charterAdr === null) {
            return 0;
        }

        // charter_adr pode ser "0080" ou "ADR-0080" — normaliza pra slug "0080-..."
        $needle = ltrim((string) $charterAdr, '0');
        if ($needle === '') {
            return 0;
        }

        // Busca ADR cujo slug começa com NNNN-
        $padded = str_pad($needle, 4, '0', STR_PAD_LEFT);
        $targetNode = KbNode::withoutGlobalScopes()
            ->where('business_id', $node->business_id)
            ->where('type', 'adr')
            ->where('slug', 'like', "{$padded}-%")
            ->first();

        if (! $targetNode) {
            return 0;
        }

        $this->upsertEdge(
            $node->business_id,
            $node->id,
            $targetNode->id,
            'charter-of',
            weight: 1.000,
            generatedBy: 'bridge_job',
            payload: ['source' => 'frontmatter:charter_adr', 'ref' => $charterAdr],
        );

        return 1;
    }

    /**
     * Auto-deriva edges cross-link a partir de `#kb-NNN` no content_md.
     *
     * NOTA: V1 usa o ID numérico de kb_nodes — `#kb-123` aponta pra node id=123.
     * Cowork tem padrão diferente (`#a01`, `#a02` legacy). Conversão pode ser
     * implementada futuramente; por ora cobrimos o padrão #kb-NNN canon.
     *
     * @return int número de edges criadas
     */
    public function deriveCrossLink(KbNode $node, McpMemoryDocument $doc): int
    {
        $content = (string) $doc->content_md;
        if ($content === '') {
            return 0;
        }

        // Regex captura #kb-NNN (NNN é id numérico). Limit pra evitar payload massivo.
        if (! preg_match_all('/#kb-(\d+)/', $content, $matches)) {
            return 0;
        }

        $ids = array_unique(array_map('intval', $matches[1] ?? []));
        if (empty($ids)) {
            return 0;
        }

        $targets = KbNode::withoutGlobalScopes()
            ->where('business_id', $node->business_id)
            ->whereIn('id', $ids)
            ->get();

        $created = 0;
        foreach ($targets as $target) {
            if ($target->id === $node->id) {
                continue; // pula self-loop
            }
            $this->upsertEdge(
                $node->business_id,
                $node->id,
                $target->id,
                'cross-link',
                weight: 1.000,
                generatedBy: 'bridge_job',
                payload: ['source' => 'content_md:#kb-NNN'],
            );
            $created++;
        }

        return $created;
    }

    /**
     * Upsert idempotente da edge — UNIQUE (business, from, to, type) garante 0 dupe.
     */
    private function upsertEdge(
        int $businessId,
        int $fromId,
        int $toId,
        string $edgeType,
        float $weight,
        string $generatedBy,
        ?array $payload = null,
    ): void {
        if ($fromId === $toId) {
            return; // CHECK constraint MySQL também bloqueia, defesa em PHP
        }

        try {
            KbEdge::withoutGlobalScopes()->updateOrCreate(
                [
                    'business_id'  => $businessId,
                    'from_node_id' => $fromId,
                    'to_node_id'   => $toId,
                    'edge_type'    => $edgeType,
                ],
                [
                    'weight'       => $weight,
                    'generated_by' => $generatedBy,
                    'payload'      => $payload,
                ],
            );
        } catch (\Throwable $e) {
            // TODO[CL]: Log+swallow ou rethrow? Por ora log+continue pra não derrubar bridge job.
            Log::warning('KbEdgeAutoDeriver: upsert edge falhou', [
                'business_id' => $businessId,
                'from'        => $fromId,
                'to'          => $toId,
                'type'        => $edgeType,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
