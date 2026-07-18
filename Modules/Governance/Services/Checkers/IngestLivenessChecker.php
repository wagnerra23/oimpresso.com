<?php

declare(strict_types=1);

namespace Modules\Governance\Services\Checkers;

use Modules\Governance\Contracts\DriftChecker;
use Modules\Governance\Services\DriftCheckResult;
use Modules\Governance\Services\DriftFinding;
use Modules\TeamMcp\Services\IngestLivenessService;

/**
 * IngestLivenessChecker — o pipe de ingestão Claude Code (MEM-CC-1) parou de receber?
 *
 * Fecha o loop anti-SPOF que o heartbeat (B-LIVE-HB, ADR 0278) e o
 * {@see IngestLivenessService} (B-LIVE-CHECK) deixaram aberto: os dois EXISTEM
 * (writer no CcIngestController + reader), mas nada ALARMAVA quando o watcher morria.
 * O único consumidor da liveness era o whats-active (passivo, só no session-start).
 * Incidente que motivou: o watcher local rodou 1× em 30/abr/2026 e nunca mais —
 * ~2,5 meses sem ingest e ninguém viu (o heartbeat nem existia quando parou).
 *
 * NÃO duplica a régua de frescor (§ proibições — não criar 2º medidor): REUSA
 * {@see IngestLivenessService::all()}/summary() (o dono da janela fresh/stale/dead).
 * Este checker só transforma o sinal em ALERTA dentro do governance:audit
 * (Centrifugo 'governance:drift' + Brief Jana), como o McpServedDriftChecker faz.
 *
 * Dispara QUANDO: existem hosts conhecidos MAS nenhum está fresco (fresh=0). Se NÃO
 * há host algum (tabela vazia/ausente — feature nunca exercida), NÃO cria lobo: clean
 * (blind ≠ dead — mesma lógica do guard B-SPOF-WA do whats-active).
 *
 * Tolerância a falha: IngestLivenessService degrada gracioso (tabela ausente →
 * all()=[]), então este checker NUNCA lança. Determinístico, sem efeito colateral.
 *
 * Severity high (pipe cego = dado stale ao time + G5 sem input) · enforcement warn ·
 * cadence daily. System-level (heartbeat é cross-tenant, sem business_id — ADR 0280).
 */
final class IngestLivenessChecker implements DriftChecker
{
    public function name(): string
    {
        return 'ingest_liveness';
    }

    public function description(): string
    {
        return 'Pipe de ingestão Claude Code (MEM-CC-1) sem heartbeat fresco — watcher caído (SPOF silencioso)';
    }

    public function tags(): array
    {
        return ['tier_1', 'infra', 'transporte', 'ingest'];
    }

    public function severity(): string
    {
        return 'high';
    }

    public function enforcement(): string
    {
        return 'warn';
    }

    public function cadence(): string
    {
        return 'daily';
    }

    public function check(array $opts = []): DriftCheckResult
    {
        $start = microtime(true);

        $svc = app(IngestLivenessService::class);
        $hosts = $svc->all();       // list<{host, last_ingest_at, status, age_minutes}>
        $summary = $svc->summary(); // {fresh, stale, dead}

        $duration = (int) round((microtime(true) - $start) * 1000);

        $meta = [
            'summary' => $summary,
            'hosts' => array_map(static function (array $h): array {
                return [
                    'host' => $h['host'],
                    'status' => $h['status'],
                    'age_minutes' => $h['age_minutes'],
                    'last_ingest_at' => $h['last_ingest_at']?->toIso8601String(),
                ];
            }, $hosts),
        ];

        // Sem host conhecido (tabela vazia/ausente) → cego, mas não há sinal pra alarmar.
        // blind ≠ dead: não cria lobo (espelha B-SPOF-WA do whats-active).
        if ($hosts === []) {
            return DriftCheckResult::clean($this->name(), $duration, $meta + ['reason' => 'no_known_hosts']);
        }

        // Algum host fresco → pipe vivo.
        if (($summary['fresh'] ?? 0) > 0) {
            return DriftCheckResult::clean($this->name(), $duration, $meta);
        }

        // Hosts existem mas NENHUM fresco → watcher(es) caído(s). ALARMA.
        $piores = array_slice(array_map(
            static fn (array $h): string => $h['host'].' ('.$h['status'].', '
                .($h['age_minutes'] !== null ? $h['age_minutes'].'min' : 'nunca').')',
            $hosts
        ), 0, 5);

        $finding = new DriftFinding(
            target: 'cc_ingest_pipeline',
            target_type: 'ingest',
            severity: 'high',
            message: 'Pipe de ingestão Claude Code SEM heartbeat fresco (fresh=0 · stale='
                .((int) ($summary['stale'] ?? 0)).' · dead='.((int) ($summary['dead'] ?? 0)).'). '
                .'Watcher(es) caído(s) — cc-search/whats-active servem dado stale, G5 sem input. '
                .'Hosts: '.implode(' · ', $piores).'. '
                .'Reativar via skill oimpresso-cc-watcher-setup (seed fresh-only + daemon).',
            evidence: $meta,
        );

        return DriftCheckResult::drifted($this->name(), [$finding], $duration, $meta);
    }
}
