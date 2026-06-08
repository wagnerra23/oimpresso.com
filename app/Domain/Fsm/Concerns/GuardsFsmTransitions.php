<?php

declare(strict_types=1);

namespace App\Domain\Fsm\Concerns;

use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use App\Domain\Fsm\Support\FsmAuthorizationFlag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * US-SELL-032 — Trait que bloqueia UPDATE direto em `current_stage_id` sem
 * passar pelo `ExecuteStageActionService`.
 *
 * Hook `updating` via static::updating(closure) — não usa Observer pra
 * evitar boot recursion (ver PR #639 hotfix).
 *
 * Autorização: ExecuteStageActionService chama
 *   FsmAuthorizationFlag::mark(Transaction::class, $tx->id)
 * antes de $tx->save(). Trait consume a flag — se ausente, lança
 * UnauthorizedActionException. Consume-once: cada save precisa flag fresh.
 *
 * Por que flag estática vs property dinâmica:
 *   Eloquent interpreta $model->X = Y como atributo persistível. Property
 *   dinâmica vai pro SQL UPDATE → "Unknown column" error em prod.
 *   Singleton estático per-request resolve sem coluna fantasma.
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

            $authorized = FsmAuthorizationFlag::consume(
                $model::class,
                $model->getKey() ?? '',
            );

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
