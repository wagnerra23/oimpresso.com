<?php

declare(strict_types=1);

namespace App\Support\Errors;

use App\Notifications\S0Alert;
use App\Util\OtelHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * ErrorReporter — os efeitos colaterais do report() (Fase 1 · E-1).
 *
 * Mantém o Handler magro (estende, não substitui): classifica → audita
 * (mcp_audit_log + log estruturado + span OTel) → dispara S0 (rate-limited).
 *
 * Resiliente por contrato: NUNCA deve lançar — um reporter que quebra derruba
 * o próprio tratamento de erro. Todo caminho de falha cai em log.
 *
 * @see prototipo-ui/handoffs/erros-fase1-classificacao.md
 */
class ErrorReporter
{
    public function __construct(
        private ErrorClassifier $classifier = new ErrorClassifier(),
        private ErrorGrouper $grouper = new ErrorGrouper(),
    ) {}

    /** Classifica + audita + dedup + (só S0) alerta. Devolve a Classification (útil pro render()). */
    public function report(Throwable $e, ?Request $request = null): Classification
    {
        $c = $this->classifier->classify($e, $request);

        // Auditoria — todas as severidades vão pro log/dashboard (OTel + mcp_audit_log).
        $this->audit($c, $e);

        // Fase 2 (E-2): deduplica em error_groups (1000 iguais = 1 linha + contador).
        // Resiliente (null se DB fora) — não bloqueia o alerta da E-1.
        $group = $this->grouper->record($c, [
            'exception' => get_class($e),
            'local'     => basename($e->getFile()).':'.$e->getLine(),
        ]);

        // Só o S0 interrompe humano — rate-limited; o alerta carrega o contador do grupo.
        if ($c->severity->interrompeHumano()) {
            $this->dispatchS0Alert($c, $group?->count);
        }

        return $c;
    }

    /**
     * Log estruturado + span OTel + insert guarded em mcp_audit_log. Sem trace/PII.
     * Nunca lança — uma auditoria que falha (ex: DB fora) NÃO pode bloquear o S0Alert.
     */
    private function audit(Classification $c, Throwable $e): void
    {
        try {
            $context = $c->toAuditArray() + [
                'exception' => get_class($e),
                'local'     => basename($e->getFile()).':'.$e->getLine(),
            ];

            Log::channel('single')->log(
                $c->severity === Severity::S0 ? 'critical' : 'error',
                'error.classified',
                $context,
            );

            // Zero-cost se OTel ausente (PII filtrado pelo próprio helper).
            OtelHelper::span('error.classified', $context, fn () => null);

            $this->writeAuditLog($c, $e);
        } catch (Throwable) {
            // Auditoria é best-effort — segue pro alerta mesmo se o log/DB falhar.
        }
    }

    /** Reusa o writer canônico do mcp_audit_log (ver UserLockoutService::auditMcp). */
    private function writeAuditLog(Classification $c, Throwable $e): void
    {
        try {
            if (! Schema::hasTable('mcp_audit_log')) {
                return;
            }

            DB::table('mcp_audit_log')->insert([
                'request_id'       => (string) Str::uuid(),
                'user_id'          => optional(auth()->user())->id,
                'business_id'      => optional(auth()->user())->business_id,
                'ts'               => now(),
                'endpoint'         => 'exception',
                'tool_or_resource' => get_class($e),
                'status'           => $c->severity->value,
                'payload_summary'  => json_encode($c->toAuditArray() + [
                    'local' => basename($e->getFile()).':'.$e->getLine(),
                ]),
                'created_at'       => now(),
            ]);
        } catch (Throwable $ex) {
            Log::warning('error.audit_failed', ['error' => $ex->getMessage()]);
        }
    }

    /**
     * Dispara S0Alert no máx 1×/dedupKey/janela (Cache::add, NUNCA Cache::flush).
     * Reincidência na janela só não repete (Fase 2 incrementa contador).
     * Sem webhook configurado → degrada pra log (skip, sem crash).
     */
    public function dispatchS0Alert(Classification $c, ?int $count = null): void
    {
        $windowMin = (int) config('errors.s0_window_minutes', 15);

        // Rate-limit: Cache::add devolve false se a chave já existe na janela.
        // Fail-open: cache indisponível → melhor alertar que silenciar um S0.
        try {
            $fresh = Cache::add('error_s0:'.$c->dedupKey, true, now()->addMinutes($windowMin));
        } catch (Throwable) {
            $fresh = true;
        }
        if (! $fresh) {
            return;
        }

        $webhook = config('errors.s0_channel');
        if (empty($webhook)) {
            Log::channel('single')->critical('error.s0.no_webhook', $c->toAuditArray());

            return;
        }

        try {
            Http::timeout(5)->post((string) $webhook, (new S0Alert($c, $count))->toWebhookPayload());
        } catch (Throwable $ex) {
            Log::channel('single')->warning('error.s0.webhook_failed', [
                'error'     => $ex->getMessage(),
                'dedup_key' => $c->dedupKey,
            ]);
        }
    }

    /**
     * O render() pro operador só assume falhas inesperadas (S0/S1) em requests
     * JSON/Inertia. Web puro usa a página de erro padrão (que já esconde trace em
     * prod); em debug o construtor mantém o trace. Nunca vaza trace pro cliente.
     */
    public static function shouldRenderOperatorMessage(Classification $c, Request $request): bool
    {
        if (config('app.debug')) {
            return false;
        }

        if (! ($request->expectsJson() || $request->header('X-Inertia'))) {
            return false;
        }

        return in_array($c->severity, [Severity::S0, Severity::S1], true);
    }
}
