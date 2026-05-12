<?php

declare(strict_types=1);

namespace App\Domain\Fsm\Observers;

use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * US-SELL-032 — Observer que bloqueia UPDATE direto em current_stage_id.
 *
 * Transforma ExecuteStageActionService em gateway OBRIGATÓRIO. Sem este
 * Observer, qualquer Controller/Job/tinker podia mudar stage via:
 *
 *   $model->current_stage_id = $novo;
 *   $model->save();
 *
 * Bypassando RBAC, side-effects, audit log do FSM canônico (ADR 0129).
 *
 * Modo de uso: models FSM-managed adicionam `use GuardsFsmTransitions;`
 * trait que registra este observer automaticamente.
 *
 * **Limitação técnica:** mass update via query builder bypassa Eloquent
 * events (`Model::where(...)->update([...])`). Detecção offline via
 * comando artisan `fsm:scan-drift` (FsmDriftDetector service).
 *
 * Escape hatch superadmin:
 *   $model->_fsmAuthorizedTransition = true;
 *   $model->current_stage_id = $novo;
 *   $model->save();
 *
 * Service canônico (ExecuteStageActionService) usa essa flag internamente.
 * Uso externo da flag deve gerar log WARNING (auditável).
 */
class TransactionFsmObserver
{
    public function updating(Model $model): void
    {
        if (! $model->isDirty('current_stage_id')) {
            return;
        }

        $authorized = (bool) ($model->_fsmAuthorizedTransition ?? false);

        if (! $authorized) {
            throw new UnauthorizedActionException(
                'Mudança direta em current_stage_id proibida — ' .
                'use ExecuteStageActionService::execute() (ADR 0129 §Service). ' .
                "Model: " . $model::class . ' #' . ($model->getKey() ?? '?')
            );
        }

        // Log da flag explícita (caso superadmin / migration de dados)
        // pra que `fsm:scan-drift` possa cruzar com sale_stage_history e
        // detectar transições não-rastreadas.
        Log::info('FSM authorized transition via flag', [
            'model' => $model::class,
            'id' => $model->getKey(),
            'from_stage_id' => $model->getOriginal('current_stage_id'),
            'to_stage_id' => $model->current_stage_id,
            'caller' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 6),
        ]);
    }
}
