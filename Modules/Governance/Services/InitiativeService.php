<?php

declare(strict_types=1);

namespace Modules\Governance\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Governance\Entities\Initiative;

/**
 * Wave 28 Agent 1 (2026-05-17) — Initiative lifecycle service (Cortex/Port.io-style).
 *
 * Responsável pelo loop completo:
 *   1. createFromScorecardBreach() — abre Initiative quando rule cai abaixo do peso target (idempotent)
 *   2. listOpen() — query base pra UI/Slack reminders
 *   3. autoClose() — marca expired quando deadline passa (alerta cross-tenant via mcp_alertas)
 *   4. syncFromScorecards() — varre últimos snapshots, abre/fecha em batch (cron daily)
 *
 * Idempotência canônica (proibição Tier 0 W28):
 *   - Não duplica Initiative pro mesmo (module, rule_id, status IN [open, in_progress])
 *   - Check WHERE antes de INSERT (não usa unique index porque status muda — UNIQUE não cobre)
 *
 * Cross-tenant: tabela é repo-wide (sem business_id). Alertas persistidos
 * em mcp_alertas com business_id=1 (superadmin Wagner — convenção mcp_* meta,
 * vide ScorecardSnapshotCommand::persistAlerts).
 *
 * @see Modules\Governance\Entities\Initiative
 * @see Modules\Governance\Console\Commands\ScorecardInitiativeSyncCommand
 */
class InitiativeService
{
    public const DEFAULT_DEADLINE_DAYS = 14;
    public const DRIFT_ALERT_KIND_EXPIRED = 'initiative_expired';

    /**
     * Cria Initiative se ainda não houver uma `open` ou `in_progress` pra (module, rule_id).
     *
     * Idempotente: chamadas repetidas retornam a Initiative existente sem duplicar.
     *
     * @param  string  $module       Nome do módulo (ex: 'Vestuario', 'Governance')
     * @param  string  $bucket       Bucket scorecard (ex: 'vertical_client_facing', 'cross_cutting_infra')
     * @param  string  $ruleId       ID da regra que quebrou (ex: 'F1.a', 'V6.b', 'D9.b')
     * @param  int     $scoreBefore  Score atual da rule (0-100)
     * @param  int     $scoreTarget  Score alvo da rule (0-100)
     * @param  int     $deadlineDays Dias até deadline (default 14 — Cortex/Port pattern)
     * @param  array   $metadata     Metadata adicional (rule_descr, source_snapshot_id, etc)
     */
    public function createFromScorecardBreach(
        string $module,
        string $bucket,
        string $ruleId,
        int $scoreBefore,
        int $scoreTarget,
        int $deadlineDays = self::DEFAULT_DEADLINE_DAYS,
        array $metadata = []
    ): Initiative {
        // Idempotência — busca Initiative open/in_progress existente
        $existing = Initiative::query()
            ->where('module', $module)
            ->where('rule_id', $ruleId)
            ->whereIn('status', Initiative::STATUSES_OPEN_LIKE)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return Initiative::create([
            'module' => $module,
            'bucket' => $bucket,
            'rule_id' => $ruleId,
            'titulo' => sprintf('[%s] Rule %s abaixo do alvo (%d→%d)', $module, $ruleId, $scoreBefore, $scoreTarget),
            'descricao' => sprintf(
                "Scorecard rule `%s` no módulo %s caiu pra %d pts (alvo: %d). " .
                "Initiative aberta auto via `governance:initiative-sync`. " .
                "Deadline %d dias. Feche elevando score >= alvo ou cancele se invalidada.",
                $ruleId,
                $module,
                $scoreBefore,
                $scoreTarget,
                $deadlineDays
            ),
            'status' => Initiative::STATUS_OPEN,
            'deadline' => now()->addDays($deadlineDays)->toDateString(),
            'score_before' => $this->clampScore($scoreBefore),
            'score_target' => $this->clampScore($scoreTarget),
            'opened_at' => now(),
            'metadata' => array_merge([
                'source' => 'governance:initiative-sync',
                'created_via' => 'scorecard_breach',
            ], $metadata),
        ]);
    }

    /**
     * Lista Initiatives open/in_progress, opcionalmente filtradas por bucket.
     */
    public function listOpen(?string $bucket = null): Collection
    {
        $query = Initiative::query()->open()->orderBy('deadline', 'asc');

        if ($bucket !== null && $bucket !== '') {
            $query->forBucket($bucket);
        }

        return $query->get();
    }

    /**
     * Marca como `expired` toda Initiative open cuja deadline passou.
     * Persiste alerta cross-tenant em mcp_alertas (kind='initiative_expired').
     *
     * @return int Quantidade marcada expired
     */
    public function autoClose(): int
    {
        $overdue = Initiative::query()->overdue()->get();
        $count = 0;

        foreach ($overdue as $initiative) {
            $initiative->update([
                'status' => Initiative::STATUS_EXPIRED,
                'closed_at' => now(),
                'metadata' => array_merge((array) $initiative->metadata, [
                    'expired_at' => now()->toIso8601String(),
                    'expired_reason' => 'deadline_passed',
                ]),
            ]);
            $this->persistAlertExpired($initiative);
            $count++;
        }

        return $count;
    }

    /**
     * Varre últimos scorecard_runs (último snapshot por módulo) e:
     *   - Abre Initiative pra cada rule abaixo do peso target (idempotent)
     *   - Fecha Initiative open onde score_after >= target
     *   - Marca expired Initiatives com deadline passada (delega autoClose)
     *
     * Retorna estatísticas pra command/log.
     *
     * @return array{opened: int, closed: int, expired: int, skipped: int}
     */
    public function syncFromScorecards(): array
    {
        $stats = ['opened' => 0, 'closed' => 0, 'expired' => 0, 'skipped' => 0];

        if (! Schema::hasTable('mcp_scorecard_runs')) {
            Log::warning('InitiativeService::syncFromScorecards — mcp_scorecard_runs nao existe.');
            $stats['skipped']++;
            return $stats;
        }

        // Último snapshot por módulo (subquery: max(id) por module hoje ou ontem)
        $latestSnapshots = DB::table('mcp_scorecard_runs as a')
            ->select('a.id', 'a.module', 'a.bucket', 'a.score', 'a.breakdown_json', 'a.snapshot_date')
            ->whereIn('a.id', function ($q) {
                $q->selectRaw('MAX(id)')
                    ->from('mcp_scorecard_runs')
                    ->groupBy('module');
            })
            ->get();

        foreach ($latestSnapshots as $snapshot) {
            $breakdown = json_decode((string) $snapshot->breakdown_json, true) ?: [];
            $rules = $this->extractRulesFromBreakdown($breakdown);

            foreach ($rules as $rule) {
                $ruleId = (string) $rule['rule_id'];
                $score = (int) $rule['score'];
                $target = (int) $rule['target'];

                if ($score < $target) {
                    // Breach — abre (idempotent)
                    $initiative = $this->createFromScorecardBreach(
                        (string) $snapshot->module,
                        (string) $snapshot->bucket,
                        $ruleId,
                        $score,
                        $target,
                        self::DEFAULT_DEADLINE_DAYS,
                        ['source_snapshot_id' => (int) $snapshot->id]
                    );
                    // Conta como "opened" apenas se foi criada agora (opened_at = recente)
                    if ($initiative->wasRecentlyCreated) {
                        $stats['opened']++;
                    }
                } else {
                    // Score recuperou — fecha qualquer Initiative open pro par (module, rule_id)
                    $closed = Initiative::query()
                        ->where('module', (string) $snapshot->module)
                        ->where('rule_id', $ruleId)
                        ->whereIn('status', Initiative::STATUSES_OPEN_LIKE)
                        ->get();

                    foreach ($closed as $init) {
                        $init->update([
                            'status' => Initiative::STATUS_DONE,
                            'closed_at' => now(),
                            'score_after' => $this->clampScore($score),
                            'metadata' => array_merge((array) $init->metadata, [
                                'closed_at_iso' => now()->toIso8601String(),
                                'closed_reason' => 'score_recovered',
                                'score_after_snapshot_id' => (int) $snapshot->id,
                            ]),
                        ]);
                        $stats['closed']++;
                    }
                }
            }
        }

        // Marca expired tudo que passou deadline
        $stats['expired'] = $this->autoClose();

        return $stats;
    }

    /**
     * Extrai array de rules normalizadas do breakdown JSON do scorecard run.
     * Tolera múltiplos formatos (ScopedScorecardEvaluator pode evoluir).
     *
     * Formato esperado:
     *   - $breakdown['rules'] = [['rule_id' => 'F1.a', 'score' => N, 'target' => M], ...]
     *   - OU $breakdown['bucket_dimensions'] = [['rule_id' => ..., 'score' => ..., 'weight' => ...], ...]
     *
     * @return array<int, array{rule_id: string, score: int, target: int}>
     */
    private function extractRulesFromBreakdown(array $breakdown): array
    {
        $rules = [];

        // Formato canônico W23+ — array 'rules'
        if (isset($breakdown['rules']) && is_array($breakdown['rules'])) {
            foreach ($breakdown['rules'] as $rule) {
                if (! is_array($rule)) {
                    continue;
                }
                $ruleId = (string) ($rule['rule_id'] ?? $rule['id'] ?? '');
                if ($ruleId === '') {
                    continue;
                }
                $score = (int) ($rule['score'] ?? 0);
                $target = (int) ($rule['target'] ?? $rule['weight'] ?? 100);
                $rules[] = [
                    'rule_id' => $ruleId,
                    'score' => $score,
                    'target' => $target,
                ];
            }
        }

        // Fallback — bucket_dimensions (ScopedScorecardEvaluator pode emitir esse)
        if (empty($rules) && isset($breakdown['bucket_dimensions']) && is_array($breakdown['bucket_dimensions'])) {
            foreach ($breakdown['bucket_dimensions'] as $dim) {
                if (! is_array($dim)) {
                    continue;
                }
                $ruleId = (string) ($dim['rule_id'] ?? $dim['id'] ?? '');
                if ($ruleId === '') {
                    continue;
                }
                $rules[] = [
                    'rule_id' => $ruleId,
                    'score' => (int) ($dim['score'] ?? 0),
                    'target' => (int) ($dim['weight'] ?? 100),
                ];
            }
        }

        return $rules;
    }

    private function persistAlertExpired(Initiative $initiative): void
    {
        if (! Schema::hasTable('mcp_alertas')) {
            return;
        }

        DB::table('mcp_alertas')->insert([
            'business_id' => 1, // mcp_* meta: superadmin Wagner repo-wide
            'kind' => self::DRIFT_ALERT_KIND_EXPIRED,
            'threshold' => 0,
            'canal' => 'log',
            'ativo' => true,
            'config_extra' => json_encode([
                'initiative_id' => $initiative->id,
                'module' => $initiative->module,
                'rule_id' => $initiative->rule_id,
                'titulo' => $initiative->titulo,
                'deadline' => $initiative->deadline?->toDateString(),
                'score_before' => $initiative->score_before,
                'score_target' => $initiative->score_target,
                'source' => 'governance:initiative-sync',
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function clampScore(int $score): int
    {
        return max(0, min(65535, $score));
    }
}
