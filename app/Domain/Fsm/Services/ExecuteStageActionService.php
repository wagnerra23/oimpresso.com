<?php

declare(strict_types=1);

namespace App\Domain\Fsm\Services;

use App\Domain\Fsm\Contracts\SideEffectInterface;
use App\Domain\Fsm\Exceptions\InvalidActionForCurrentStageException;
use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use App\Domain\Fsm\Models\SaleStageAction;
use App\Domain\Fsm\Models\SaleStageHistory;
use App\Domain\Fsm\Support\FsmAuthorizationFlag;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Jana\Scopes\ScopeByBusiness;

/**
 * Service canônico de execução de transição FSM (ADR 0129 §Service).
 *
 * 6 responsabilidades sequenciais:
 *   1. Resolve action válida pra $subject->current_stage_id + $actionKey
 *   2. Valida tenancy (subject.business_id == process.business_id) + bloqueia terminal
 *   3. Checa RBAC via $user->hasAnyRole($action->roles)
 *   4. Executa side-effect dentro de DB::transaction (atomicidade)
 *   5. Atualiza $subject->current_stage_id (se target_stage_id != null)
 *   6. Dispara event (se event_class definido) + loga em sale_stage_history (sempre)
 */
class ExecuteStageActionService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(Model $subject, string $actionKey, ?User $user = null, array $payload = []): SaleStageHistory
    {
        $user ??= auth()->user();
        $currentStageId = $subject->current_stage_id ?? null;

        $action = SaleStageAction::with(['stage.process', 'roles'])
            ->where('stage_id', $currentStageId)
            ->where('key', $actionKey)
            ->first();

        if (! $action) {
            throw new InvalidActionForCurrentStageException(
                "Action '{$actionKey}' não existe pra stage_id={$currentStageId}"
            );
        }

        if ($action->stage->is_terminal) {
            throw new InvalidActionForCurrentStageException(
                "Stage '{$action->stage->key}' é terminal — não aceita novas transições"
            );
        }

        if (($subject->business_id ?? null) !== $action->stage->process->business_id) {
            throw new UnauthorizedActionException(
                'Cross-tenant attempt: subject.business_id != process.business_id'
            );
        }

        $roleNames = $action->roles->pluck('role_name')->all();

        // US-SELL-031 — fail-secure: action is_critical=true SEM role configurada
        // bloqueia execução. Seeds incompletos viravam bypass silencioso pra
        // actions de risco (cancelar_venda, voltar_para_orcamento, iniciar_producao).
        if (empty($roleNames) && ($action->is_critical ?? false)) {
            throw new UnauthorizedActionException(
                "Action '{$actionKey}' é crítica e exige role explícita — " .
                "nenhuma role configurada bloqueia execução por segurança"
            );
        }

        if (! empty($roleNames) && (! $user || ! $user->hasAnyRole($roleNames))) {
            throw new UnauthorizedActionException(
                "User não tem nenhuma das roles exigidas: " . implode(',', $roleNames)
            );
        }

        $mergedPayload = array_merge($action->side_effect_payload ?? [], $payload);

        return DB::transaction(function () use ($subject, $action, $user, $mergedPayload, $currentStageId) {
            if ($action->side_effect_class) {
                /** @var SideEffectInterface $sideEffect */
                $sideEffect = app($action->side_effect_class);
                $sideEffect->execute($subject, $mergedPayload);
            }

            if ($action->target_stage_id !== null) {
                // US-SELL-032 — flag estática autoriza UPDATE em current_stage_id
                // (consumida pelo trait GuardsFsmTransitions). Singleton evita
                // property dinâmica no Eloquent que viraria coluna fantasma.
                FsmAuthorizationFlag::mark($subject::class, $subject->getKey());
                $subject->current_stage_id = $action->target_stage_id;
                $subject->save();
            }

            if ($action->event_class) {
                event(new $action->event_class($subject, $action, $user));
            }

            return SaleStageHistory::withoutGlobalScope(ScopeByBusiness::class)->create([
                'business_id' => $subject->business_id,
                'transaction_id' => $subject->getKey(),
                'action_id' => $action->id,
                'from_stage_id' => $currentStageId,
                'to_stage_id' => $action->target_stage_id,
                'user_id' => $user?->id,
                'payload_snapshot' => $mergedPayload,
                'executed_at' => now(),
            ]);
        });
    }
}
