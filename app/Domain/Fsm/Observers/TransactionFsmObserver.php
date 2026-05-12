<?php

declare(strict_types=1);

namespace App\Domain\Fsm\Observers;

use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use App\Domain\Fsm\Support\FsmAuthorizationFlag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * US-SELL-032 — Observer alternativo que bloqueia UPDATE direto em
 * `current_stage_id` pra Models que NÃO possam usar o trait
 * `App\Domain\Fsm\Concerns\GuardsFsmTransitions` (ex: subjects de teste,
 * Models legacy fora do nosso controle).
 *
 * **Em prod a proteção canônica vem do trait** aplicado no Model
 * `App\Transaction` + `Modules\Repair\Entities\JobSheet` — este Observer
 * existe pra cenários satélite (testes Pest, Models de migração externa,
 * spike scripts). Service `ExecuteStageActionService` usa
 * `FsmAuthorizationFlag::mark()` pra autorizar — TANTO o trait COMO o
 * observer consumem do mesmo singleton, sem duplicar lógica.
 *
 * **NÃO usa property dinâmica `_fsmAuthorizedTransition` no Model** porque
 * Eloquent interpretaria como atributo persistível e geraria SQL UPDATE
 * "Unknown column" (lição hotfix #640 — 2026-05-12, ver memory/proibicoes.md
 * §FSM Pipeline Canônico). Singleton `FsmAuthorizationFlag` é o pattern
 * canônico per-request consume-once.
 *
 * Mass updates (`Model::where(...)->update([...])`) e raw `DB::table()->update`
 * **bypassam Eloquent events** — limitação técnica documentada. Detecção
 * offline via `php artisan fsm:scan-drift transactions` (cron daily 03:00 BRT).
 *
 * Registro:
 *   $model::observe(TransactionFsmObserver::class);
 * (NÃO use `static::observe(...)` em `bootXxx()` de trait — recursion
 * LogicException, lição hotfix #639.)
 */
class TransactionFsmObserver
{
    public function updating(Model $model): void
    {
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
    }
}
