<?php

declare(strict_types=1);

namespace App\Support\Errors;

use App\Jobs\ReprocessJob;
use App\Util\OtelHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * AutoResolver — a política de auto-resolução (Fase 2 · E-3).
 *
 * "O erro que se resolve sozinho não acorda ninguém." Decide o que é recuperável
 * (whitelist S1/S2 por dono), enfileira o reprocesso ({@see ReprocessJob}) com
 * backoff exponencial, registra o "% auto-resolvido" e — quando o retry esgota —
 * faz o dead-letter: promove pra S1 (vira problema de humano) e FECHA o auto-loop.
 *
 * Invariantes (handoff):
 *   - S0 NUNCA auto-resolve (dinheiro/dado/segurança = humano sempre · ADR 0284 §4).
 *   - Sem retry infinito (dead-letter promove pra S1 e para).
 *   - Idempotência é obrigatória — garantida pelo {@see ReprocessJob} + ação de domínio.
 *
 * NUNCA lança em caminho de métrica/promoção — um resolvedor que quebra agrava o
 * próprio erro que tentava absorver.
 *
 * @see prototipo-ui/handoffs/erros-autoresolucao.md
 * @see prototipo-ui/handoffs/erros-fase1-classificacao.md
 */
final class AutoResolver
{
    public function __construct(
        /** Reusa o pipeline E-1/E-2 (classify → audita → dedup) pra promover o dead-letter. */
        private ErrorReporter $reporter = new ErrorReporter(),
    ) {}

    /**
     * Recuperável por retry automático? Whitelist: S1/S2 + dono na lista.
     * S0 nunca (Tier 0); S3 é ruído conhecido — não vale auto-retry.
     */
    public function canRetry(Classification $c): bool
    {
        if (! (bool) config('errors.auto_resolve.enabled', true)) {
            return false;
        }

        // S0 NUNCA auto-resolve. Inegociável (Regra Mestre Tier 0 · ADR 0284 §4).
        if ($c->severity === Severity::S0) {
            return false;
        }

        // Só os recuperáveis do Mapa (S1/S2). S3 = ruído, não entra no loop.
        if (! in_array($c->severity, [Severity::S1, Severity::S2], true)) {
            return false;
        }

        return in_array($c->owner, $this->whitelistOwners(), true);
    }

    /**
     * Ponto de entrada do domínio: se recuperável, enfileira o reprocesso idempotente
     * e devolve true (operador vê "enfileirado", não erro). Caso contrário, false —
     * o chamador segue pro tratamento humano (E-1/E-2).
     *
     * @param  class-string  $actionClass  invocável resolvível pelo container: __invoke(array $params): void
     * @param  array<string,mixed>  $params  args serializáveis (ids, não models)
     * @param  string  $idempotencyKey  id externo — dedup do efeito (não duplicar NF-e/cobrança)
     */
    public function attempt(Classification $c, string $actionClass, array $params, string $idempotencyKey): bool
    {
        if (! $this->canRetry($c)) {
            return false;
        }

        ReprocessJob::dispatch($c, $actionClass, $params, $idempotencyKey);

        return true;
    }

    /** Teto de tentativas — sem retry infinito. */
    public function maxAttempts(): int
    {
        return max(1, (int) config('errors.auto_resolve.max_attempts', 5));
    }

    /**
     * Backoff exponencial (segundos) entre as tentativas: base · 2^(n-1), saturado no teto.
     * Devolve maxAttempts-1 esperas (entre N tentativas há N-1 intervalos).
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        $base = max(1, (int) config('errors.auto_resolve.backoff_base_seconds', 30));
        $cap = max($base, (int) config('errors.auto_resolve.backoff_max_seconds', 900));

        $waits = [];
        for ($n = 1; $n < $this->maxAttempts(); $n++) {
            $waits[] = (int) min($cap, $base * (2 ** ($n - 1)));
        }

        return $waits !== [] ? $waits : [$base];
    }

    /**
     * Auto-resolução bem-sucedida — registra (alimenta o "% auto-resolvido") SEM alerta.
     */
    public function recordResolved(Classification $c, int $attempts): void
    {
        $this->recordMetric($c, 'auto_resolved', $attempts);
    }

    /**
     * Dead-letter: o retry esgotou. Promove pra S1 (problema de humano — dashboard/dono,
     * sem paging, coerente com a régua E-1) e fecha o auto-loop. Best-effort, nunca lança.
     */
    public function deadLetter(Classification $c, Throwable $last, int $attempts): void
    {
        $this->recordMetric($c, 'auto_dead_letter', $attempts);

        try {
            // O ErrorReporter é resiliente por contrato (nunca lança).
            $this->reporter->report($this->promoteToS1($c, $last));
        } catch (Throwable $e) {
            Log::channel('single')->warning('error.auto_dead_letter.promote_failed', [
                'dedup_key' => $c->dedupKey,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Constrói uma exceção S1 auto-classificada a partir do erro recuperável esgotado. */
    private function promoteToS1(Classification $c, Throwable $last): ClassifiedError
    {
        return new class($c->owner, $c->operatorMessage, $c->dedupKey, $last) extends RuntimeException implements ClassifiedError
        {
            public function __construct(
                private readonly string $ownerName,
                private readonly string $opMsg,
                string $dedup,
                Throwable $previous,
            ) {
                parent::__construct('auto-resolução esgotada ('.$dedup.')', 0, $previous);
            }

            public function severity(): Severity
            {
                return Severity::S1;
            }

            public function audience(): Audience
            {
                return Audience::CONSTRUTOR;
            }

            public function owner(): string
            {
                return $this->ownerName;
            }

            public function operatorMessage(): string
            {
                return $this->opMsg;
            }
        };
    }

    /**
     * Métrica de auto-resolução — log estruturado + span OTel + insert guarded em
     * mcp_audit_log (mesma porta da auditoria E-1). O painel computa o "% auto-resolvido"
     * (auto_resolved ÷ (auto_resolved + auto_dead_letter)). Sem trace/PII. Nunca lança.
     */
    private function recordMetric(Classification $c, string $outcome, int $attempts): void
    {
        $ctx = $c->toAuditArray() + ['outcome' => $outcome, 'attempts' => $attempts];

        try {
            Log::channel('single')->log(
                $outcome === 'auto_resolved' ? 'info' : 'warning',
                'error.'.$outcome,
                $ctx,
            );

            OtelHelper::span('error.'.$outcome, $ctx, fn () => null);

            $this->writeAuditLog($c, $outcome, $attempts);
        } catch (Throwable) {
            // Métrica é best-effort — nunca derruba a absorção do erro.
        }
    }

    /** Insert guarded em mcp_audit_log (espelha {@see ErrorReporter::writeAuditLog}). */
    private function writeAuditLog(Classification $c, string $outcome, int $attempts): void
    {
        try {
            if (! Schema::hasTable('mcp_audit_log')) {
                return;
            }

            DB::table('mcp_audit_log')->insert([
                'request_id' => (string) Str::uuid(),
                'user_id' => optional(auth()->user())->id,
                'business_id' => optional(auth()->user())->business_id,
                'ts' => now(),
                'endpoint' => 'auto_resolve',
                'tool_or_resource' => $c->owner,
                'status' => $outcome,
                'payload_summary' => json_encode($c->toAuditArray() + ['attempts' => $attempts]),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('error.auto_resolve.audit_failed', ['error' => $e->getMessage()]);
        }
    }

    /** @return list<string> donos/domínios recuperáveis (whitelist). */
    private function whitelistOwners(): array
    {
        return array_values(array_filter((array) config('errors.auto_resolve.whitelist_owners', [])));
    }
}
