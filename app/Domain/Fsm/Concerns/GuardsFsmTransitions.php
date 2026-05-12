<?php

declare(strict_types=1);

namespace App\Domain\Fsm\Concerns;

use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * US-SELL-032 — Trait que registra hook `updating` em models FSM-managed.
 *
 * Bloqueia UPDATE direto em `current_stage_id` sem flag interna
 * `_fsmAuthorizedTransition` (que o ExecuteStageActionService seta).
 *
 * **Por que não usar `static::observe(Observer::class)` no boot:**
 * Laravel chama `static::observe()` que internamente resolve Observer via
 * `static::resolveObserverClassName` + `static::registerObserver` — esse
 * caminho dispara recursão pra inicializar a Observer class, e quebra com:
 *   "The [Model::observe] method may not be called on model X while it is being booted."
 *
 * Solução canônica: registrar event listener inline via `static::updating(...)`
 * (syntactic sugar de `static::registerModelEvent('updating', ...)`).
 *
 * Uso:
 *   class Transaction extends Model {
 *       use \App\Domain\Fsm\Concerns\GuardsFsmTransitions;
 *   }
 */
trait GuardsFsmTransitions
{
    public static function bootGuardsFsmTransitions(): void
    {
        static::updating(function (Model $model) {
            if (! $model->isDirty('current_stage_id')) {
                return;
            }

            $authorized = (bool) ($model->_fsmAuthorizedTransition ?? false);

            if (! $authorized) {
                throw new UnauthorizedActionException(
                    'Mudança direta em current_stage_id proibida — ' .
                    'use ExecuteStageActionService::execute() (ADR 0129 §Service). ' .
                    'Model: ' . $model::class . ' #' . ($model->getKey() ?? '?')
                );
            }

            Log::info('FSM authorized transition via flag', [
                'model' => $model::class,
                'id' => $model->getKey(),
                'from_stage_id' => $model->getOriginal('current_stage_id'),
                'to_stage_id' => $model->current_stage_id,
            ]);
        });
    }
}
