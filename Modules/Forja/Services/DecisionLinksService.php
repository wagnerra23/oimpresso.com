<?php

namespace Modules\Forja\Services;

use Illuminate\Support\Facades\DB;

/**
 * Vincula ADRs (do mcp_memory_documents via slug) com entidades da Forja.
 * Suporta backlinks: dado uma ADR, lista todas as entidades que a referenciam.
 *
 * **Veio do `Modules\ADS\Services` na parte 6** (remoção do núcleo dual-brain,
 * ADR 0363). Não morreu com o núcleo porque a Forja é consumidora VIVA: o
 * `ProjectDecomposerService` injeta este service no construtor e grava em
 * `mcp_decision_links` a cada decompose (auditoria reversa ADR→project).
 * Por isso `mcp_decision_links` também saiu da lista de DROP e passou a ser
 * tabela da Forja — mesma classe do achado E3, que pegou `mcp_projects`.
 *
 * Observabilidade D9.a (ADR 0155): queries lookup ms-range; Tracer via
 * `OtelHelper::span(` pode envolver quando virar hot path.
 */
class DecisionLinksService
{
    public const TARGET_PROJECT = 'project';

    public function link(string $targetType, int $targetId, string $adrSlug, string $relation = 'referenced'): void
    {
        DB::table('mcp_decision_links')->updateOrInsert(
            [
                'target_type' => $targetType,
                'target_id'   => $targetId,
                'adr_slug'    => $adrSlug,
                'relation'    => $relation,
            ],
            [
                'created_by' => 'system',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    /**
     * Backlinks: dado uma ADR, retorna todas as entidades que a referenciam.
     */
    public function backlinks(string $adrSlug): array
    {
        $rows = DB::table('mcp_decision_links')
            ->where('adr_slug', $adrSlug)
            ->orderBy('target_type')
            ->get(['target_type', 'target_id', 'relation', 'created_at']);

        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r->target_type] ??= [];

            $entity = $this->fetchTarget($r->target_type, (int) $r->target_id);
            if (! $entity) continue;

            $grouped[$r->target_type][] = [
                'id'         => $r->target_id,
                'relation'   => $r->relation,
                'created_at' => $r->created_at,
                'label'      => $entity['label'],
                'url'        => $entity['url'],
            ];
        }

        return $grouped;
    }

    /**
     * Forward links: dado um target, lista ADRs vinculadas.
     * @return array<array{adr_slug:string, relation:string}>
     */
    public function forward(string $targetType, int $targetId): array
    {
        return DB::table('mcp_decision_links')
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->orderBy('adr_slug')
            ->get(['adr_slug', 'relation'])
            ->map(fn ($r) => ['adr_slug' => $r->adr_slug, 'relation' => $r->relation])
            ->all();
    }

    /**
     * Linka pelo array de strings tipo ["ADR 0011", "ADR 0024", "DESIGN.md"].
     * Faz parse heurístico; só linka quando consegue identificar um slug.
     */
    public function linkFromTexts(string $targetType, int $targetId, array $textRefs, string $relation = 'referenced'): int
    {
        $linked = 0;
        foreach ($textRefs as $text) {
            $slug = $this->extractSlug($text);
            if (! $slug) continue;
            $this->link($targetType, $targetId, $slug, $relation);
            $linked++;
        }
        return $linked;
    }

    /**
     * Extrai slug de texto. Aceita:
     *   "ADR 0011"           → 0011-*
     *   "ADR 0024 (BaseInst)" → 0024-*
     *   "0046-chat-agent-gap" → 0046-chat-agent-gap (literal)
     *   "DESIGN.md"           → null (não é ADR)
     */
    private function extractSlug(string $text): ?string
    {
        // Match "ADR 0011" (4 dígitos) com fuzzy match no DB depois
        if (preg_match('/ADR\s*(\d{4})/i', $text, $m)) {
            $padded = $m[1];
            // Procura no mcp_memory_documents um doc cujo slug começa com NNNN-
            $found = DB::table('mcp_memory_documents')
                ->where('slug', 'like', $padded . '-%')
                ->orWhere('slug', $padded)
                ->value('slug');
            return $found;
        }

        // Slug literal "0046-chat-agent-gap" ou "ARQ-0001-..."
        if (preg_match('/^[A-Z0-9\-]+\d{4}[\-a-z0-9]*$/i', trim($text))) {
            $exists = DB::table('mcp_memory_documents')
                ->where('slug', $text)
                ->exists();
            return $exists ? $text : null;
        }

        return null;
    }

    private function fetchTarget(string $type, int $id): ?array
    {
        switch ($type) {
            case self::TARGET_PROJECT:
                $r = DB::table('mcp_projects')->where('id', $id)->first(['codigo', 'nome']);
                if (! $r) return null;
                return ['label' => "{$r->codigo} — {$r->nome}", 'url' => "/ads/admin/projects/{$id}"];

            // Os 3 outros alvos morreram com o núcleo do ADS (parte 6, ADR 0363)
            // e por isso NÃO foram portados: `skill` lia `mcp_decision_patterns` e
            // `decision` lia `mcp_dual_brain_decisions` — ambas dropadas; `metaskill`
            // lia `mcp_governance_rules`, que sobrevive no Governance, mas a URL
            // `/ads/admin/meta-skills` não. Linha remanescente em `mcp_decision_links`
            // com esses `target_type` cai no `default` e some da UI — que é o
            // comportamento honesto quando a entidade-alvo deixou de existir.
            default:
                return null;
        }
    }
}
