<?php

declare(strict_types=1);

namespace App\Domain\Fsm\Services;

use App\Domain\Fsm\Contracts\SideEffectInterface;
use App\Domain\Fsm\Exceptions\InvalidActionForCurrentStageException;
use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use App\Domain\Fsm\Models\SaleStageAction;
use App\Domain\Fsm\Models\SaleStageHistory;
use App\Domain\Fsm\Policies\StageActionPolicy;
use App\Domain\Fsm\Support\FsmAuthorizationFlag;
use App\User;
use App\Util\OtelHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Jana\Scopes\ScopeByBusiness;

/**
 * Service canônico de execução de transição FSM (ADR 0129 §Service).
 *
 * 6 responsabilidades sequenciais:
 *   1. Resolve action válida pra $subject->current_stage_id + $actionKey
 *   2. Valida tenancy (subject.business_id == process.business_id) + bloqueia terminal
 *   3. Checa RBAC: permission module-level (StageActionPolicy::grantsByPermission,
 *      ADR 0265) OU $user->hasAnyRole($action->roles)
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
        return OtelHelper::spanBiz('fsm.execute_action', function () use ($subject, $actionKey, $user, $payload): SaleStageHistory {
            return $this->executeInternal($subject, $actionKey, $user, $payload);
        }, [
            'action_key' => $actionKey,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->getKey(),
            'business_id' => $subject->business_id ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function executeInternal(Model $subject, string $actionKey, ?User $user, array $payload): SaleStageHistory
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

        // ADR 0265 (fio usável) — permission module-level destrava o subject (hoje:
        // ServiceOrder via oficinaauto.service_order.update). MESMA regra da
        // StageActionPolicy::canExecute (UI) — policy e service nunca divergem,
        // senão a UI mostra o botão e o execute() responde 403. Roles Spatie seguem
        // valendo como caminho alternativo (restrição adicional, não muro default).
        $grantedByPermission = app(StageActionPolicy::class)->grantsByPermission($user, $subject);

        // US-SELL-031 — fail-secure: action is_critical=true SEM role configurada
        // bloqueia execução. Seeds incompletos viravam bypass silencioso pra
        // actions de risco (cancelar_venda, voltar_para_orcamento, iniciar_producao).
        // Mensagem instrutiva cita tabela e caminho de fix pra desbloquear o dev.
        if (! $grantedByPermission && empty($roleNames) && ($action->is_critical ?? false)) {
            throw new UnauthorizedActionException(
                "Action crítica '{$actionKey}' exige role configurada em " .
                "sale_stage_action_roles. Adicione role no seeder ou via UI " .
                "antes de executar (fail-secure US-SELL-031)."
            );
        }

        if (! $grantedByPermission && ! empty($roleNames) && (! $user || ! $user->hasAnyRole($roleNames))) {
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
