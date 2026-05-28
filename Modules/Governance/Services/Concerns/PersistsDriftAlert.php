<?php

declare(strict_types=1);

namespace Modules\Governance\Services\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Governance\Services\DriftFinding;

/**
 * Trait reusável pra persistir findings em mcp_alertas_eventos.
 *
 * Extraído de DetectDriftCommand::persistirAlerta() (407 linhas, sofisticado:
 * schema mapping, chave_idempotencia <=200 chars, fallback Log channel, evita
 * race via firstOrCreate-equivalent SELECT antes INSERT).
 *
 * Convenções:
 * - chave_idempotencia: '<tipo>:<target_type>:<target_hash>:<YYYY-MM-DD>' (truncado 200)
 * - tipo: 'drift_<checker_name>' (ex: 'drift_secrets_audit', 'drift_module_scope')
 * - business_id: NULL pra repo-wide; finding pode sobrescrever pra per-business
 * - status: sempre 'aberto' no insert; ack/resolved é manual via UI Governance
 * - target hash: sha1(target)[:12] pra evitar overflow de path longo
 *
 * ADR 0216 §Trait PersistsDriftAlert
 */
trait PersistsDriftAlert
{
    /**
     * Persiste finding idempotentemente. Retorna id do alerta ou null se falhou.
     *
     * Idempotência diária: 2x mesmo dia com mesmo (checker, target) NÃO duplica.
     * Dia seguinte com mesmo drift = NOVO alerta (não spam, mas mostra recorrência).
     */
    public function persistirDriftAlert(
        string $checkerName,
        DriftFinding $finding,
    ): ?int {
        $diaUtc = now()->format('Y-m-d');
        $targetHash = substr(sha1($finding->target), 0, 12);
        $chave = sprintf(
            'drift_%s:%s:%s:%s',
            $checkerName,
            $finding->target_type,
            $targetHash,
            $diaUtc,
        );
        $chave = mb_substr($chave, 0, 200); // Pegadinha §4.8 — schema UNIQUE 200 chars

        try {
            $existing = DB::table('mcp_alertas_eventos')
                ->where('chave_idempotencia', $chave)
                ->value('id');
            if ($existing !== null) {
                return (int) $existing;
            }

            $id = DB::table('mcp_alertas_eventos')->insertGetId([
                'user_id' => null,
                'business_id' => $finding->business_id, // null = repo-wide per ADR 0093 §Exceção
                'tipo' => "drift_{$checkerName}",
                'severidade' => $this->mapSeveridadeToCanonical($finding->severity),
                'titulo' => $this->buildAlertTitle($checkerName, $finding),
                'descricao' => $finding->message,
                'chave_idempotencia' => $chave,
                'metadata' => json_encode([
                    'checker' => $checkerName,
                    'target' => $finding->target,
                    'target_type' => $finding->target_type,
                    'severity' => $finding->severity,
                    'evidence' => $finding->evidence,
                    'detected_at' => now()->toIso8601String(),
                ], JSON_UNESCAPED_UNICODE),
                'status' => 'aberto',
                'criado_em' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return (int) $id;
        } catch (\Throwable $e) {
            Log::channel('single')->error('governance:audit — falha ao persistir drift alert', [
                'checker' => $checkerName,
                'target' => $finding->target,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function buildAlertTitle(string $checkerName, DriftFinding $finding): string
    {
        $shortTarget = mb_strimwidth($finding->target, 0, 80, '…');

        return sprintf('Drift [%s] — %s', $checkerName, $shortTarget);
    }

    /**
     * Map severity DriftChecker (Datadog 5-níveis) → mcp_alertas_eventos.severidade
     * (schema canon: low|medium|high|critical).
     *
     * 'info' do checker vira 'low' no DB (não há 'info' no enum).
     */
    private function mapSeveridadeToCanonical(string $severity): string
    {
        return match (strtolower($severity)) {
            'critical' => 'critical',
            'high' => 'high',
            'medium' => 'medium',
            'low', 'info' => 'low',
            default => 'medium',
        };
    }
}
