<?php

declare(strict_types=1);

namespace App\Domain\Fsm\SideEffects;

use App\Domain\Fsm\Contracts\SideEffectInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;
use Modules\Jana\Scopes\ScopeByBusiness;

/**
 * Wave 5-A — Side-effect "Concluir serviço de manutenção" (process `cacamba_manutencao`).
 *
 * Action `concluir` (stage `em_servico` → `concluida`, marcada `is_critical=true`).
 *
 * Subject = ServiceOrder (order_type='manutencao'). Vehicle volta `disponivel`
 * APENAS se este foi o último serviço de manutenção ativo. Verificação:
 *   - Conta outras OS order_type='manutencao' status NOT IN ('concluida','cancelada')
 *     pra mesmo vehicle_id no mesmo business.
 *   - Se 0 → vehicle.current_status = 'disponivel'
 *   - Se >0 → mantém 'manutencao' (outras OS ainda ativas)
 *
 * Decisão: idempotente — recalcula sempre. Não confia em estado anterior do
 * Vehicle (defesa contra drift se outra OS modificou status enquanto esta tava
 * em em_servico).
 *
 * Multi-tenant Tier 0 (ADR 0093): query filtra business_id explícito.
 *
 * Refs:
 *   - ADR 0143 §FSM Pipeline LIVE prod biz=1
 *   - SPEC.md US-OFICINA-003
 *   - Wave 5-A PR #723 (cadastro action `concluir` is_critical=true)
 */
final class ConcluirServicoCacamba implements SideEffectInterface
{
    public function execute(Model $subject, array $payload = []): void
    {
        if (! $subject instanceof ServiceOrder) {
            throw new InvalidArgumentException(
                'ConcluirServicoCacamba: subject deve ser Modules\\OficinaAuto\\Entities\\ServiceOrder (recebido '
                . $subject::class . ')'
            );
        }

        $businessId = (int) $subject->business_id;
        $serviceOrderId = (int) $subject->getKey();
        $vehicleId = (int) $subject->vehicle_id;

        if ($vehicleId <= 0) {
            throw new InvalidArgumentException(
                "ConcluirServicoCacamba: ServiceOrder {$serviceOrderId} não tem vehicle_id válido"
            );
        }

        $vehicle = Vehicle::withoutGlobalScope(ScopeByBusiness::class)
            ->where('id', $vehicleId)
            ->where('business_id', $businessId)
            ->first();

        if (! $vehicle) {
            throw new InvalidArgumentException(
                "ConcluirServicoCacamba: vehicle {$vehicleId} não encontrado no business {$businessId}"
            );
        }

        if (! Schema::hasColumn('vehicles', 'current_status')) {
            Log::warning('ConcluirServicoCacamba: coluna vehicles.current_status ausente — Wave 5-A migration pendente', [
                'business_id' => $businessId,
                'service_order_id' => $serviceOrderId,
                'vehicle_id' => $vehicleId,
            ]);
            return;
        }

        // Recalcula: ainda há outras OS de manutenção ativas pra este vehicle?
        $orderTypeColumn = Schema::hasColumn('service_orders', 'order_type');

        $outrasManutencoes = DB::table('service_orders')
            ->where('business_id', $businessId)
            ->where('vehicle_id', $vehicleId)
            ->where('id', '!=', $serviceOrderId)
            ->whereNotIn('status', ['concluida', 'cancelada'])
            ->whereNull('deleted_at')
            ->when($orderTypeColumn, fn ($q) => $q->where('order_type', 'manutencao'))
            ->count();

        if ($outrasManutencoes === 0) {
            // Nenhuma outra manutenção ativa — vehicle volta disponível
            DB::table('vehicles')
                ->where('id', $vehicleId)
                ->where('business_id', $businessId)
                ->update([
                    'current_status' => 'disponivel',
                    'updated_at' => now(),
                ]);

            Log::info('ConcluirServicoCacamba: serviço concluído, caçamba liberada pra disponível', [
                'business_id' => $businessId,
                'service_order_id' => $serviceOrderId,
                'vehicle_id' => $vehicleId,
                'plate' => $vehicle->plate,
            ]);
        } else {
            Log::info('ConcluirServicoCacamba: serviço concluído mas vehicle permanece em manutenção (outras OS ativas)', [
                'business_id' => $businessId,
                'service_order_id' => $serviceOrderId,
                'vehicle_id' => $vehicleId,
                'plate' => $vehicle->plate,
                'outras_manutencoes_ativas' => $outrasManutencoes,
            ]);
        }
    }
}
