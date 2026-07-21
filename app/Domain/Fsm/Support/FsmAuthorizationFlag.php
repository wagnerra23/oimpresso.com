<?php

declare(strict_types=1);

namespace App\Domain\Fsm\Support;

/**
 * US-SELL-032 — Flag estática consumível pra autorizar mudança de
 * `current_stage_id` via ExecuteStageActionService.
 *
 * Por que NÃO property dinâmica no Model:
 *   Eloquent interpreta qualquer property atribuída via $model->X = Y
 *   como atributo persistível. Se setarmos $model->_fsmAuthorizedTransition,
 *   Laravel inclui isso no SQL UPDATE → "Unknown column" error.
 *
 * Solução: singleton estático scoped por (Model class + Model id).
 * - mark(class, id) marca uma única transição autorizada
 * - consume(class, id) retorna true E remove a marca (uso único)
 *
 * Vantagens:
 *   - Sem coluna fantasma no Eloquent
 *   - Consume-once: cada transição precisa flag fresh do Service
 *
 * Ciclo de vida do estado estático (IMPORTANTE — parecer juiz 2026-07-21):
 *   `$authorized` é `static` — vive enquanto o PROCESSO PHP viver, NÃO
 *   "entre requests" por mágica. O reset depende do runtime:
 *   - PHP-FPM / artisan one-shot: o processo morre a cada request/comando,
 *     então o estado some por conta do runtime (é o caso do ERP web hoje).
 *   - Octane worker / Horizon / `queue:work` PERSISTENTE: o processo NÃO
 *     morre entre requests/jobs → o estado NÃO some sozinho. Um mark() não
 *     consumido (exceção antes do save, self-transition não-dirty, ou Titulo
 *     sem guard) vazaria autorização pro request/job SEGUINTE do worker.
 *   Por isso o reset é LIGADO EXPLICITAMENTE ao início de cada unidade de
 *   trabalho — tornando o per-request scope verdadeiro em QUALQUER runtime:
 *     - Octane: listener ResetFsmAuthorizationFlag em RequestReceived/
 *       TaskReceived/TickReceived (config/octane.php)
 *     - Fila:   Queue::before(...) em AppServiceProvider (antes de cada job)
 *   Catraca: tests/Feature/Domain/Fsm/FsmFlagResetLifecycleTest.php
 */
final class FsmAuthorizationFlag
{
    /** @var array<string, bool> chave = "$modelClass:$modelId" */
    private static array $authorized = [];

    public static function mark(string $modelClass, int|string $modelId): void
    {
        self::$authorized[self::key($modelClass, $modelId)] = true;
    }

    public static function consume(string $modelClass, int|string $modelId): bool
    {
        $key = self::key($modelClass, $modelId);
        $authorized = self::$authorized[$key] ?? false;
        unset(self::$authorized[$key]);
        return $authorized;
    }

    /**
     * Test helper: limpa todas as flags (útil em tests entre cenários).
     */
    public static function reset(): void
    {
        self::$authorized = [];
    }

    private static function key(string $modelClass, int|string $modelId): string
    {
        return "{$modelClass}:{$modelId}";
    }
}
