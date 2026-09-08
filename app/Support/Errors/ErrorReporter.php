<?php

declare(strict_types=1);

namespace App\Support\Errors;

use App\Notifications\S0Alert;
use App\Util\OtelHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ErrorReporter — os efeitos colaterais do report() (Fase 1 · E-1).
 *
 * Mantém o Handler magro (estende, não substitui): classifica → audita
 * (log estruturado + span OTel) → agrupa em error_groups → dispara S0
 * (rate-limited).
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

        // Auditoria — todas as severidades vão pro log estruturado + span OTel.
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
     * Log estruturado + span OTel. Sem trace/PII.
     * Nunca lança — uma auditoria que falha (ex: DB fora) NÃO pode bloquear o S0Alert.
     *
     * NÃO escreve mais em `mcp_audit_log` desde 2026-09-08 — ver o porquê logo abaixo,
     * onde o `writeAuditLog` existia. A trilha por-erro vive em `error_groups`
     * (ErrorGrouper, chamado no report()), que é o dono do tema e deduplica.
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
        } catch (Throwable) {
            // Auditoria é best-effort — segue pro alerta mesmo se o log/OTel falhar.
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Por que o writeAuditLog() saiu daqui (2026-09-08)
    |--------------------------------------------------------------------------
    | Ele inseria uma linha por exceção em `mcp_audit_log`. Medido em prod (SHA
    | 3e9f463e54), esse insert tinha DOIS defeitos — e o barulhento não era o pior:
    |
    | (a) `user_id` é NOT NULL com FK pra `users`, e o valor vinha de
    |     `optional(auth()->user())->id`. Em contexto de CRON não há usuário
    |     autenticado, então o insert estourava SQLSTATE[23000] e a linha se perdia.
    |     20.021 ocorrências de `error.audit_failed` no laravel.log.
    |
    | (b) Silencioso e pior: gravava `endpoint => 'exception'` e
    |     `status => 'S0'..'S3'`, valores que NÃO existem nos ENUMs dessas colunas
    |     (`enum('tools/list','tools/call',...)` e `enum('ok','denied','error',
    |     'quota_exceeded')`). Como o `strict` do config/database.php é false, o
    |     MySQL não recusa: coage pra ''. Resultado — 165 linhas gravadas com
    |     endpoint='' e status='', severidade destruída na COLUNA (sobrevivia só
    |     dentro do JSON de payload_summary).
    |
    | Consertar (a) sozinho converteria 20 mil linhas PERDIDAS em 20 mil linhas
    | CORROMPIDAS. E consertar os dois exigiria mexer no schema de uma tabela Tier 0
    | (append-only, com triggers de imutabilidade e hash-chain da ADR 0294) que a
    | proibicoes.md lista explicitamente em "onde NÃO inventar".
    |
    | A remoção não perde informação porque `mcp_audit_log` NUNCA foi o dono deste
    | tema. Todo consumidor dele o lê como razão de uso/custo MCP — sum('custo_brl'),
    | count() de calls, distinct user_id de usuários ativos, topTools (TeamController,
    | ForjaSaudeService, ScorecardBuilderService). Nenhum lê linha de exceção; as 165
    | só POLUÍAM essas métricas, inflando "calls MCP" e "usuários ativos".
    |
    | O dono do tema é `error_groups` (ErrorGrouper, chamado no report() logo acima),
    | que já capturava tudo isso deduplicado — inclusive os erros de cron que (a)
    | perdia. Em prod: 64 grupos com severidade, contador e janela.
    |
    | As 165 linhas antigas ficam onde estão: a tabela é append-only por lei.
    */

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
