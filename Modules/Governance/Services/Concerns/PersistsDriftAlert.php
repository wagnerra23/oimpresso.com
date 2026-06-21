<?php

declare(strict_types=1);

namespace Modules\Governance\Services\Concerns;

use Carbon\Carbon;
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
     *
     * Escalonamento por persistência (Onda 1 — sentinela transporte): se o MESMO drift
     * (checker+target_type+target) já tem alerta-evento ABERTO há mais de N dias
     * (`governance.drift_escalation_days`, default 3), a severidade EFETIVA do novo
     * registro é elevada (warn→high / high→critical) e o metadata ganha `escalated=true`
     * + `first_seen_at` + `dias_aberto`, pra que `governance:audit --notify` dispare um
     * alerta ATIVO (Centrifugo) em vez de só persistir mais um diário ignorável.
     *
     * ADITIVO/retrocompatível: drift novo (sem histórico) segue o caminho de sempre,
     * com `escalated=false`. Reusa `created_at` existente — sem coluna/migration nova.
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

            // Escalonamento: primeira ocorrência ABERTA deste MESMO drift (ignora o
            // sufixo de data da chave — a chave é diária, então alerta de 4 dias atrás
            // tem chave diferente; buscamos pelo prefixo chave/tipo + target estável).
            $primeiraOcorrencia = $this->primeiraOcorrenciaAberta($checkerName, $finding, $targetHash);
            $diasAberto = $primeiraOcorrencia !== null
                ? (int) Carbon::parse($primeiraOcorrencia)->diffInDays(now())
                : 0;
            $limiteDias = (int) config('governance.drift_escalation_days', 3);
            $escalated = $primeiraOcorrencia !== null && $diasAberto > $limiteDias;

            $severityEfetiva = $escalated
                ? $this->escalarSeveridade($finding->severity)
                : $finding->severity;

            $metadata = [
                'checker' => $checkerName,
                'target' => $finding->target,
                'target_type' => $finding->target_type,
                'severity' => $finding->severity,
                'evidence' => $finding->evidence,
                'detected_at' => now()->toIso8601String(),
                // Escalonamento (Onda 1) — sempre presente; false no caso comum.
                'escalated' => $escalated,
            ];
            if ($escalated) {
                $metadata['severity_efetiva'] = $severityEfetiva;
                $metadata['first_seen_at'] = Carbon::parse($primeiraOcorrencia)->toIso8601String();
                $metadata['dias_aberto'] = $diasAberto;
            }

            $id = DB::table('mcp_alertas_eventos')->insertGetId([
                'user_id' => null,
                'business_id' => $finding->business_id, // null = repo-wide per ADR 0093 §Exceção
                'tipo' => "drift_{$checkerName}",
                'severidade' => $this->mapSeveridadeToCanonical($severityEfetiva),
                'titulo' => ($escalated ? '[ESCALADO] ' : '').$this->buildAlertTitle($checkerName, $finding),
                'descricao' => $finding->message,
                'chave_idempotencia' => $chave,
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
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
     * `created_at` (string) da PRIMEIRA ocorrência ABERTA deste mesmo drift, ou null.
     *
     * "Mesmo drift" = mesmo tipo (`drift_<checker>`) + target_type + target estável
     * (casado por `metadata->target` = $finding->target). NÃO usa a chave diária (que
     * muda todo dia), por isso casa pelo target dentro do metadata. Pega o `created_at`
     * mais antigo entre os abertos pra medir HÁ QUANTOS DIAS o drift persiste.
     */
    private function primeiraOcorrenciaAberta(string $checkerName, DriftFinding $finding, string $targetHash): ?string
    {
        try {
            return DB::table('mcp_alertas_eventos')
                ->where('tipo', "drift_{$checkerName}")
                ->where('status', 'aberto')
                ->where('chave_idempotencia', 'like', "drift_{$checkerName}:{$finding->target_type}:{$targetHash}:%")
                ->min('created_at');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Eleva a severidade efetiva 1 nível no escalonamento por persistência:
     * info→low→medium→warn→high→critical (warn é nível de checker, mapeado p/ high).
     * 'critical' já é o teto. Mantém o input se desconhecido (defensivo).
     */
    private function escalarSeveridade(string $severity): string
    {
        return match (strtolower($severity)) {
            'info' => 'low',
            'low' => 'medium',
            'medium' => 'high',
            'warn', 'high' => 'critical',
            'critical' => 'critical',
            default => $severity,
        };
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
