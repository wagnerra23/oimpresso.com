<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Services\Forja;

use Modules\TeamMcp\Entities\CoworkHandoff;
use Modules\TeamMcp\Entities\McpIngestHeartbeat;

/**
 * ForjaMcpService — projeção dos handoffs de design (Cowork→Code, F1→F3) pra aba
 * MCP do cockpit Forja (Fase 1 · ADR 0283).
 *
 * Surface-only: a Fase 0 já criou tabela/tools/cron ({@see CoworkHandoff},
 * {@see \Modules\TeamMcp\Mcp\Tools\HandoffPendingTool}). Aqui só se LÊ a fonte da
 * verdade (`cowork_handoffs`) + o heartbeat do ingest e devolve o estado pro
 * front. NÃO muta nada — re-disparar/supersede é roteado pelas tools MCP e o
 * merge é o 1-clique do [W] no GitHub (sem auto-merge — ADR 0283).
 *
 * Espelha ForjaQuadroService/ForjaBacklogService/ForjaChangelogService: read-only,
 * sem dado fantasma. Tier 0 (ADR 0093): `cowork_handoffs`/`mcp_ingest_heartbeat`
 * são REPO-WIDE (artefato do repo, não de tenant) — sem business_id por design,
 * igual {@see \Modules\TeamMcp\Mcp\Tools\HandoffPendingTool}.
 */
class ForjaMcpService
{
    /** Teto de handoffs projetados (o loop é baixo-volume por design). */
    private const LIMIT = 200;

    /** Pending mais velho que isto vira 'stale' na LEITURA (robusto, não espera o cron). */
    private const STALE_AFTER_DAYS = 3;

    /** Heartbeat mais antigo que isto = "transporte sem sinal" (alerta, não "tudo calmo"). */
    private const HEARTBEAT_SILENT_MINUTES = 60;

    /**
     * Os handoffs projetados: o mais recente por slug (maior version), EXCLUINDO
     * 'superseded'. Status 'stale' derivado na leitura; gate derivado do
     * `gate_status` (mesma regra verde do handoff-ack).
     *
     * @return list<array<string,mixed>>
     */
    public function handoffs(): array
    {
        $tenancy = 'business_id'; // marker NoMissingTenantScopeRule — cowork_handoffs é repo-wide (ADR 0093/0283), sem tenant por design

        return CoworkHandoff::query()
            ->where('status', '!=', 'superseded')
            ->orderByDesc('version')
            ->get()
            ->unique('slug')               // maior version por slug (lista já vem version desc)
            ->sortByDesc(fn (CoworkHandoff $h) => optional($h->created_at)->getTimestamp() ?? 0)
            ->take(self::LIMIT)
            ->map(fn (CoworkHandoff $h): array => $this->serialize($h))
            ->values()
            ->all();
    }

    /**
     * Heartbeat do ingest (último `last_ingest_at` entre os hosts) — pro
     * empty-state distinguir "ocioso" de "transporte sem sinal".
     *
     * @return array<string,mixed>
     */
    public function heartbeat(): array
    {
        $tenancy = 'business_id'; // marker NoMissingTenantScopeRule — mcp_ingest_heartbeat é repo-wide (ADR 0093), sem tenant por design

        $hb = McpIngestHeartbeat::query()->orderByDesc('last_ingest_at')->first();
        $last = $hb?->last_ingest_at;

        return [
            'last_ingest_at'    => optional($last)->toIso8601String(),
            'last_ingest_human' => $last !== null ? $last->diffForHumans() : null,
            'host'              => $hb?->host,
            // "transporte sem sinal": sem heartbeat OU mudo além do teto.
            'silent'            => $last === null || $last->lt(now()->subMinutes(self::HEARTBEAT_SILENT_MINUTES)),
        ];
    }

    /**
     * Serializa 1 handoff pro front — sem o corpo inteiro (só a 1ª linha como resumo).
     *
     * @return array<string,mixed>
     */
    private function serialize(CoworkHandoff $h): array
    {
        $files = is_array($h->files_json) ? $h->files_json : [];

        return [
            'slug'             => $h->slug,
            'version'          => (int) $h->version,
            'tela'             => $h->tela,
            'status'           => $this->displayStatus($h),  // 'stale' derivado na leitura
            'files_count'      => count($files),
            'pr_url'           => $h->pr_url,
            'created_at'       => optional($h->created_at)->toIso8601String(),
            'created_at_human' => optional($h->created_at)->diffForHumans(),
            'created_by'       => $h->created_by,
            'gate'             => $this->deriveGate($h),      // verde/vermelho/rodando/na
            'gate_status'      => is_array($h->gate_status) ? $h->gate_status : null,
            'signed'           => is_string($h->sig) && $h->sig !== '',
            'resumo'           => $this->firstLine((string) $h->body_md),
        ];
    }

    /** Status de exibição: 'pending' velho (> N dias) vira 'stale' — cron-independente. */
    private function displayStatus(CoworkHandoff $h): string
    {
        if (
            $h->status === 'pending'
            && $h->created_at !== null
            && $h->created_at->lt(now()->subDays(self::STALE_AFTER_DAYS))
        ) {
            return 'stale';
        }

        return $h->status;
    }

    /**
     * Gate badge derivado do `gate_status` — MESMA regra verde do handoff-ack
     * (conformance && critique_score>=80 && a11y). Nunca pinta verde sem ler.
     *
     *   verde    = os 3 ok
     *   vermelho = algum falhou
     *   rodando  = applied mas sem gate_status reportado ainda
     *   na       = não-avaliado (pending/stale sem ack)
     */
    private function deriveGate(CoworkHandoff $h): string
    {
        $g = is_array($h->gate_status) ? $h->gate_status : null;

        if ($g === null || $g === []) {
            return $h->status === 'applied' ? 'rodando' : 'na';
        }

        $green = (bool) ($g['conformance'] ?? false)
            && ((int) ($g['critique_score'] ?? 0) >= 80)
            && (bool) ($g['a11y'] ?? false);

        return $green ? 'verde' : 'vermelho';
    }

    /** 1ª linha não-vazia do body_md (sem marcação md) — resumo curto (teto 140). */
    private function firstLine(string $bodyMd): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $bodyMd) ?: [];
        foreach ($lines as $line) {
            $clean = trim((string) preg_replace('/^[#>\-*\s]+/', '', $line));
            if ($clean !== '') {
                return mb_strlen($clean) > 140 ? mb_substr($clean, 0, 140) . '…' : $clean;
            }
        }

        return '';
    }
}
