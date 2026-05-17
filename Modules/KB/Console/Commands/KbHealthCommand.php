<?php

declare(strict_types=1);

namespace Modules\KB\Console\Commands;

use App\Util\OtelHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\KB\Services\KbBridgeStateService;
use Modules\KB\Services\KbCorpusBuilder;

/**
 * kb:health-check — Wave 25 KB §G saturação D9 — checks RAG saúde.
 *
 * Roda 4 checks SQL/serviço por business e retorna JSON estruturado:
 *
 *  1. corpus_size       — total kb_nodes ativos no business
 *  2. bridge_freshness  — last_bridge_at vs threshold (default 24h)
 *  3. retrieval_latency — corpusVersionHash ping (proxy de health Meilisearch+DB)
 *  4. editable_ratio    — % nodes editáveis vs bridge canon (saúde curadoria)
 *
 * Cada check produz status `ok|warn|fail` + métrica numérica + nota.
 * Exit code agregado: 0 todos ok/warn; 1 algum fail.
 *
 * Não chama LLM (sem custo). Latency proxy via DB queries do CorpusBuilder.
 *
 * Compatível com pattern `jana:health-check` (CLAUDE.md §Métricas saúde) — JSON
 * shape estável pra ingestão em dashboard Cockpit V2 / alerta cron.
 *
 * Multi-tenant Tier 0 (ADR 0093 §"Commands & Jobs sem HTTP context"):
 *   --business-id obrigatório quando rodado isolado.
 *   --all-businesses itera Business::active() pra cron daily.
 *
 * Uso:
 *   php artisan kb:health-check --business-id=1
 *   php artisan kb:health-check --business-id=1 --detail
 *   php artisan kb:health-check --business-id=1 --json
 *   php artisan kb:health-check --all-businesses --json     # cron
 *
 * Wave 25 D9.a — wrap em OtelHelper::span pra correlate health-check latency
 * com eventos de drift/corruption do corpus.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md §"Loop fechado por métrica"
 * @see Modules/KB/Services/KbCorpusBuilder.php
 */
class KbHealthCommand extends Command
{
    protected $signature = 'kb:health-check
                            {--business-id= : Business ID (obrigatório se --all-businesses ausente)}
                            {--all-businesses : Itera Business::active() — usar em cron daily}
                            {--bridge-threshold-h=24 : Threshold horas pra bridge stale alert}
                            {--detail : Log detalhado por check}
                            {--json : Output JSON estruturado (cron-friendly)}';

    protected $description = 'Health-check KB — corpus size + bridge freshness + retrieval latency + ratio curadoria.';

    public function handle(KbBridgeStateService $bridgeState): int
    {
        if ($this->option('all-businesses')) {
            return $this->handleAllBusinesses($bridgeState);
        }

        $bizId = (int) $this->option('business-id');
        if ($bizId <= 0) {
            $this->error('--business-id obrigatório (multi-tenant Tier 0 ADR 0093) — ou use --all-businesses');
            return 1;
        }

        $result = OtelHelper::span('kb.health.check', [
            'module'      => 'KB',
            'business_id' => $bizId,
        ], fn () => $this->runChecks($bizId, $bridgeState));

        return $this->emit($result);
    }

    /**
     * Roda todos os businesses ativos (cron daily). Falha se QUALQUER um
     * tiver check `fail` — facilita alerta agregado.
     */
    protected function handleAllBusinesses(KbBridgeStateService $bridgeState): int
    {
        $businesses = DB::table('business')
            ->where(function ($q) {
                $q->whereNull('deleted_at')
                  ->orWhere('deleted_at', '');
            })
            ->pluck('id');

        $aggregate = [];
        $exit = 0;

        foreach ($businesses as $bizId) {
            $bizId = (int) $bizId;
            $result = OtelHelper::span('kb.health.check', [
                'module'      => 'KB',
                'business_id' => $bizId,
            ], fn () => $this->runChecks($bizId, $bridgeState));

            $aggregate[$bizId] = $result;
            if (($result['overall'] ?? 'ok') === 'fail') {
                $exit = 1;
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode($aggregate, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            foreach ($aggregate as $bizId => $r) {
                $this->line(sprintf('[biz=%d] overall=%s', $bizId, $r['overall']));
            }
        }

        return $exit;
    }

    /**
     * Executa os 4 checks pra 1 business. Sempre retorna array shape estável
     * (independente de erro) — fail-open por check pra agregação não quebrar.
     *
     * @return array{
     *   business_id: int,
     *   overall: string,
     *   checks: array<string, array{status:string, value:int|float|string|null, note:string}>
     * }
     */
    protected function runChecks(int $bizId, KbBridgeStateService $bridgeState): array
    {
        $thresholdH = max(1, (int) $this->option('bridge-threshold-h'));
        $checks = [];

        // 1. corpus_size — total kb_nodes ativos
        $checks['corpus_size'] = $this->checkCorpusSize($bizId);

        // 2. bridge_freshness — quando rodou último bridge job
        $checks['bridge_freshness'] = $this->checkBridgeFreshness($bizId, $bridgeState, $thresholdH);

        // 3. retrieval_latency — proxy via corpusVersionHash (queries SQL ~5-15ms esperado)
        $checks['retrieval_latency'] = $this->checkRetrievalLatency($bizId);

        // 4. editable_ratio — % editable vs bridge (saúde curadoria)
        $checks['editable_ratio'] = $this->checkEditableRatio($bizId);

        $overall = 'ok';
        foreach ($checks as $c) {
            if ($c['status'] === 'fail') {
                $overall = 'fail';
                break;
            }
            if ($c['status'] === 'warn' && $overall !== 'fail') {
                $overall = 'warn';
            }
        }

        $result = [
            'business_id' => $bizId,
            'overall'     => $overall,
            'checks'      => $checks,
        ];

        if ($this->option('detail')) {
            Log::channel('copiloto-ai')->info('kb:health-check', $result);
        }

        return $result;
    }

    /**
     * @return array{status:string,value:int,note:string}
     */
    protected function checkCorpusSize(int $bizId): array
    {
        try {
            $count = (int) DB::table('kb_nodes')
                ->where('business_id', $bizId)
                ->whereNull('deleted_at')
                ->count();

            $status = match (true) {
                $count <= 0   => 'fail',
                $count < 10   => 'warn',
                default       => 'ok',
            };

            return [
                'status' => $status,
                'value'  => $count,
                'note'   => $status === 'fail'
                    ? 'KB vazio — sem nodes ativos no business'
                    : ($status === 'warn' ? 'KB pequeno (<10 nodes)' : "Corpus saudável: {$count} nodes"),
            ];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'value' => 0, 'note' => 'Erro DB: ' . $e->getMessage()];
        }
    }

    /**
     * @return array{status:string,value:int|null,note:string}
     */
    protected function checkBridgeFreshness(int $bizId, KbBridgeStateService $bridgeState, int $thresholdH): array
    {
        try {
            $lastAt = $bridgeState->getLastBridgeAt($bizId);
            if ($lastAt === null) {
                return [
                    'status' => 'warn',
                    'value'  => null,
                    'note'   => 'Bridge nunca rodou (primeira sync pendente)',
                ];
            }

            $ageH = (int) $lastAt->diffInHours(now());
            $status = match (true) {
                $ageH > ($thresholdH * 3) => 'fail',
                $ageH > $thresholdH       => 'warn',
                default                   => 'ok',
            };

            return [
                'status' => $status,
                'value'  => $ageH,
                'note'   => sprintf(
                    'Bridge rodou há %dh (threshold %dh)',
                    $ageH,
                    $thresholdH,
                ),
            ];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'value' => null, 'note' => 'Erro: ' . $e->getMessage()];
        }
    }

    /**
     * @return array{status:string,value:int,note:string}
     */
    protected function checkRetrievalLatency(int $bizId): array
    {
        try {
            $start = microtime(true);
            $corpus = new KbCorpusBuilder($bizId);
            $corpus->corpusVersionHash(); // proxy de saúde DB+queries
            $ms = (int) round((microtime(true) - $start) * 1000);

            $status = match (true) {
                $ms > 1000 => 'fail',     // 1s+ pra hash agregado é grave (DB lenta)
                $ms > 200  => 'warn',     // 200ms+ degradado
                default    => 'ok',
            };

            return [
                'status' => $status,
                'value'  => $ms,
                'note'   => "corpus_version_hash em {$ms}ms (proxy DB+aggregation)",
            ];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'value' => 0, 'note' => 'Erro: ' . $e->getMessage()];
        }
    }

    /**
     * @return array{status:string,value:float,note:string}
     */
    protected function checkEditableRatio(int $bizId): array
    {
        try {
            $total = (int) DB::table('kb_nodes')
                ->where('business_id', $bizId)
                ->whereNull('deleted_at')
                ->count();

            if ($total === 0) {
                return ['status' => 'warn', 'value' => 0.0, 'note' => 'Sem nodes pra calcular ratio'];
            }

            $editable = (int) DB::table('kb_nodes')
                ->where('business_id', $bizId)
                ->whereNull('deleted_at')
                ->where('is_editable', true)
                ->count();

            $ratio = round($editable / $total, 4);

            // Ratio saudável é 5-60%. Acima sugere falta de bridge canon.
            // Abaixo de 1% sugere KB inerte (só docs read-only).
            $status = match (true) {
                $ratio > 0.95 || $ratio < 0.01 => 'warn',
                default                         => 'ok',
            };

            return [
                'status' => $status,
                'value'  => $ratio,
                'note'   => sprintf(
                    'editable=%d / total=%d (%.1f%%)',
                    $editable,
                    $total,
                    $ratio * 100,
                ),
            ];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'value' => 0.0, 'note' => 'Erro: ' . $e->getMessage()];
        }
    }

    /**
     * Renderiza output (JSON ou tabela). Retorna exit code agregado.
     */
    protected function emit(array $result): int
    {
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->line(sprintf('[biz=%d] overall=%s', $result['business_id'], $result['overall']));
            foreach ($result['checks'] as $name => $c) {
                $this->line(sprintf('  - %s: %s (%s) — %s', $name, $c['status'], $c['value'] ?? 'n/a', $c['note']));
            }
        }

        return $result['overall'] === 'fail' ? 1 : 0;
    }
}
