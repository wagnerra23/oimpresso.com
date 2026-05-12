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
 *   - Per-request scope (static reset entre requests PHP-FPM/Octane)
 *   - Consume-once: cada transição precisa flag fresh do Service
 *
 * Limitação:
 *   - Não persiste entre requests (não deveria — flag autorizativa
 *     transitória)
 *   - Em jobs em fila, ExecuteStageActionService roda dentro do Job
 *     worker, mesmo PHP process — funciona
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
