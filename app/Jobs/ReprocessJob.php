<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\Errors\AutoResolver;
use App\Support\Errors\Classification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ReprocessJob — reprocessador idempotente da auto-resolução (Fase 2 · E-3).
 *
 * Genérico por design: executa uma AÇÃO de domínio resolvível pelo container
 * (`__invoke(array $params): void`) com retry + backoff exponencial da fila e,
 * ao esgotar, dead-letter via {@see AutoResolver}. Casos do Mapa:
 *   - SEFAZ fora → reemite a NF-e quando voltar (operador vê "enfileirado").
 *   - Webhook de pagamento atrasado → reprocessa (dedup por id externo).
 *   - Baileys desconectou → tenta reconectar N×; mensagens enfileiram.
 *
 * Idempotência (OBRIGATÓRIA — não duplicar NF-e/cobrança):
 *   1. Fast-path: marca `reprocess_done:<idempotencyKey>` no Cache após o sucesso;
 *      uma re-execução (entrega dupla / retry após sucesso não-ack) faz short-circuit.
 *   2. Garantia DURÁVEL é da própria AÇÃO (dedup por id externo — ex: unique no DB).
 *      O Cache é só o atalho barato; a ação NÃO pode confiar só nele.
 *
 * Sem retry infinito: `$tries` é o teto (config); esgotou → {@see failed()} promove S1.
 *
 * Em prod, aponte `errors.auto_resolve.connection` pra a conexão `reprocess`
 * (retry_after alto · @see config/queue.php) — senão o worker pode reclamar um job
 * em backoff e re-executá-lo antes do tempo, furando a idempotência.
 *
 * @see prototipo-ui/handoffs/erros-autoresolucao.md
 */
class ReprocessJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Teto de tentativas — sem retry infinito (config errors.auto_resolve.max_attempts). */
    public int $tries;

    public int $timeout = 120;

    /**
     * @param  class-string  $actionClass  invocável resolvível: __invoke(array $params): void
     * @param  array<string,mixed>  $params  args serializáveis (ids/strings, NÃO models)
     * @param  string  $idempotencyKey  id externo — chave de dedup do efeito
     */
    public function __construct(
        public readonly Classification $classification,
        public readonly string $actionClass,
        public readonly array $params,
        public readonly string $idempotencyKey,
    ) {
        $this->tries = max(1, (int) config('errors.auto_resolve.max_attempts', 5));

        if ($conn = config('errors.auto_resolve.connection')) {
            $this->onConnection((string) $conn);
        }
        if ($queue = config('errors.auto_resolve.queue')) {
            $this->onQueue((string) $queue);
        }
    }

    /**
     * Backoff exponencial (segundos) entre tentativas — fonte única: {@see AutoResolver}.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return app(AutoResolver::class)->backoff();
    }

    public function handle(AutoResolver $resolver): void
    {
        $doneKey = 'reprocess_done:'.$this->idempotencyKey;

        // Idempotência (fast-path): já concluído → não reaplica o efeito.
        try {
            if (Cache::has($doneKey)) {
                Log::channel('single')->info('error.reprocess.skipped_idempotent', [
                    'idempotency_key' => $this->idempotencyKey,
                    'dedup_key' => $this->classification->dedupKey,
                ]);

                return;
            }
        } catch (Throwable) {
            // Cache indisponível → segue; a ação tem o dedup durável por id externo.
        }

        // Executa a ação de domínio. Se lançar, a fila reagenda com backoff até esgotar $tries.
        $action = app($this->actionClass);
        $action($this->params);

        // Sucesso: fecha a janela de duplicação ANTES da métrica (que nunca lança).
        try {
            Cache::put($doneKey, true, now()->addDay());
        } catch (Throwable $e) {
            Log::channel('single')->warning('error.reprocess.done_marker_failed', [
                'idempotency_key' => $this->idempotencyKey,
                'error' => $e->getMessage(),
            ]);
        }

        $resolver->recordResolved($this->classification, $this->attemptsSafe());
    }

    /**
     * Dead-letter: a fila esgotou $tries. Promove pra S1 e para (sem retry infinito).
     * Laravel chama isto uma vez, após a última tentativa falhar.
     */
    public function failed(Throwable $e): void
    {
        app(AutoResolver::class)->deadLetter($this->classification, $e, $this->attemptsSafe());
    }

    /** attempts() depende do job da fila; em execução direta (teste) devolve 1. */
    private function attemptsSafe(): int
    {
        try {
            return $this->job !== null ? max(1, $this->attempts()) : 1;
        } catch (Throwable) {
            return 1;
        }
    }
}
