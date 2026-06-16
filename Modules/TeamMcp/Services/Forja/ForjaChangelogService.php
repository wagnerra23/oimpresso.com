<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Services\Forja;

use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpCcSession;
use Modules\Jana\Entities\Mcp\McpMemoryDocument;

/**
 * ForjaChangelogService — aba Changelog do cockpit Forja (/forja/changelog).
 *
 * Projeta "o que shippou" SÓ a partir de fonte real no DB — ADRs/SPECs
 * (mcp_memory_documents) + sessões Claude Code do time (mcp_cc_sessions),
 * mescladas e ordenadas por data desc. PRs e Ondas NÃO têm fonte fácil/
 * confiável no DB neste recorte, então são OMITIDOS (sem dado fantasma —
 * mesma disciplina da Triagem em ForjaController).
 *
 * Multi-tenant Tier 0 (ADR 0093 / ADR 0070): mcp_memory_documents e
 * mcp_cc_sessions são REPO-WIDE cross-tenant POR DESIGN (governança da
 * plataforma, não per-business) — sem filtro business_id, INTENCIONAL,
 * igual ForjaController / ScopedScorecard / TriageController.
 */
class ForjaChangelogService
{
    /** Teto de linhas projetadas (lista mescla ADR + sessão). */
    private const MAX_ENTRIES = 30;

    /** Quanto puxar de cada fonte antes de mesclar/cortar (folga p/ ordenação). */
    private const PER_SOURCE = 40;

    /**
     * Linhas do changelog (máx ~30), ordenadas por data desc.
     *
     * Shape de cada item: ['kind','id','title','actor','date'] — date em ISO 8601.
     *
     * @return array<int, array{kind:string, id:string, title:string, actor:string, date:string}>
     */
    public function build(): array
    {
        $entries = array_merge($this->adrEntries(), $this->sessionEntries());

        // Ordena por data desc (string ISO/date ordena lexicograficamente; itens
        // sem data vão pro fim) e corta no teto.
        usort($entries, static fn (array $a, array $b): int => strcmp($b['date'], $a['date']));

        return array_slice($entries, 0, self::MAX_ENTRIES);
    }

    /**
     * ADRs/SPECs aceitos (mcp_memory_documents type in [adr,spec]) por decided_at desc.
     *
     * @return array<int, array{kind:string, id:string, title:string, actor:string, date:string}>
     */
    private function adrEntries(): array
    {
        $tenancy = 'business_id'; // marker NoMissingTenantScopeRule — mcp_* repo-wide (ADR 0070/0093), sem tenant por design

        if (! Schema::hasTable('mcp_memory_documents')) {
            return [];
        }

        return McpMemoryDocument::query()
            ->whereIn('type', ['adr', 'spec'])
            ->orderByDesc('decided_at')
            ->limit(self::PER_SOURCE)
            ->get(['slug', 'type', 'title', 'decided_at', 'decided_by'])
            ->map(function (McpMemoryDocument $d): array {
                // decided_by é cast array (frontmatter); 1º autor vira o selo de ator.
                $by = is_array($d->decided_by) ? $d->decided_by : [];
                $actor = isset($by[0]) && trim((string) $by[0]) !== ''
                    ? (string) $by[0]
                    : strtoupper($d->type);

                return [
                    'kind'  => 'adr',
                    'id'    => $this->adrId($d),
                    'title' => (string) $d->title,
                    'actor' => $actor,
                    'date'  => optional($d->decided_at)->toIso8601String() ?? '',
                ];
            })
            ->all();
    }

    /**
     * Sessões Claude Code do time (mcp_cc_sessions) por started_at desc.
     *
     * @return array<int, array{kind:string, id:string, title:string, actor:string, date:string}>
     */
    private function sessionEntries(): array
    {
        $tenancy = 'business_id'; // marker NoMissingTenantScopeRule — mcp_* repo-wide (ADR 0070/0093), sem tenant por design

        if (! Schema::hasTable('mcp_cc_sessions')) {
            return [];
        }

        return McpCcSession::query()
            ->orderByDesc('started_at')
            ->limit(self::PER_SOURCE)
            ->get(['session_uuid', 'summary_auto', 'started_at', 'git_branch', 'metadata'])
            ->map(function (McpCcSession $s): array {
                $title = trim((string) ($s->summary_auto ?? ''));

                return [
                    'kind'  => 'session',
                    'id'    => $this->sessionId($s),
                    'title' => $title !== '' ? $title : 'Sessão Claude Code',
                    'actor' => $this->sessionActor($s),
                    'date'  => optional($s->started_at)->toIso8601String() ?? '',
                ];
            })
            ->all();
    }

    /** ID curto da ADR/SPEC: slug se houver, senão "ADR/SPEC <título>" como fallback. */
    private function adrId(McpMemoryDocument $d): string
    {
        $slug = trim((string) ($d->slug ?? ''));
        if ($slug !== '') {
            return $slug;
        }

        return strtoupper((string) $d->type);
    }

    /** ID curto da sessão: 8 primeiros chars do uuid (igual list_sessions/cc-search). */
    private function sessionId(McpCcSession $s): string
    {
        $uuid = (string) ($s->session_uuid ?? '');

        return $uuid !== '' ? substr($uuid, 0, 8) : 'sess';
    }

    /**
     * Selo de ator da sessão: lê do dado se houver (metadata.actor), senão 'CL'
     * (Claude — sem inventar nome de pessoa quando o dado não traz).
     */
    private function sessionActor(McpCcSession $s): string
    {
        $meta = is_array($s->metadata) ? $s->metadata : [];
        $actor = isset($meta['actor']) ? trim((string) $meta['actor']) : '';

        return $actor !== '' ? $actor : 'CL';
    }
}
