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
 * Wave 5-A — Side-effect "Cancelar serviço de manutenção" (process `cacamba_manutencao`).
 *
 * Action `cancelar` (qualquer stage → `cancelada`, marcada `is_critical=true`).
 *
 * Subject = ServiceOrder (order_type='manutencao'). Comportamento:
 *   - Se vehicle.current_status='manutencao' por causa desta OS específica
 *     (current_rental_id NÃO bate, manutenção não usa rental_id)
 *   - Recalcula igual ConcluirServicoCacamba: ainda há outras OS manutenção ativas?
 *   - 0 → vehicle volta disponivel
 *   - >0 → mantém manutencao
 *
 * Decisão: usa mesmo recálculo que ConcluirServicoCacamba — defesa contra drift
 * e consistência de comportamento (cancelar e concluir têm efeito idêntico no
 * Vehicle, só audit log fica diferente).
 *
 * Multi-tenant Tier 0 (ADR 0093): query filtra business_id explícito.
 *
 * Refs:
 *   - ADR 0143 §FSM Pipeline LIVE prod biz=1
 *   - SPEC.md US-OFICINA-003
 *   - Wave 5-A PR #723 (cadastro action `cancelar` is_critical=true)
 */
final class CancelarServicoCacamba implements SideEffectInterface
{
    public function execute(Model $subject, array $payload = []): void
    {
        if (! $subject instanceof ServiceOrder) {
            throw new InvalidArgumentException(
                'CancelarServicoCacamba: subject deve ser Modules\\OficinaAuto\\Entities\\ServiceOrder (recebido '
                . $subject::class . ')'
            );
        }

        $businessId = (int) $subject->business_id;
        $serviceOrderId = (int) $subject->getKey();
        $vehicleId = (int) $subject->vehicle_id;
        $motivo = (string) ($payload['motivo'] ?? 'Cancelamento via FSM');

        if ($vehicleId <= 0) {
            throw new InvalidArgumentException(
                "CancelarServicoCacamba: ServiceOrder {$serviceOrderId} não tem vehicle_id válido"
            );
        }

        $vehicle = Vehicle::withoutGlobalScope(ScopeByBusiness::class)
            ->where('id', $vehicleId)
            ->where('business_id', $businessId)
            ->first();

        if (! $vehicle) {
            throw new InvalidArgumentException(
                "CancelarServicoCacamba: vehicle {$vehicleId} não encontrado no business {$businessId}"
            );
        }

        if (! Schema::hasColumn('vehicles', 'current_status')) {
            Log::warning('CancelarServicoCacamba: coluna vehicles.current_status ausente — Wave 5-A migration pendente', [
                'business_id' => $businessId,
                'service_order_id' => $serviceOrderId,
                'vehicle_id' => $vehicleId,
            ]);
            return;
        }

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
            DB::table('vehicles')
                ->where('id', $vehicleId)
                ->where('business_id', $businessId)
                ->update([
                    'current_status' => 'disponivel',
                    'updated_at' => now(),
                ]);

            Log::info('CancelarServicoCacamba: serviço cancelado, caçamba liberada pra disponível', [
                'business_id' => $businessId,
                'service_order_id' => $serviceOrderId,
                'vehicle_id' => $vehicleId,
                'plate' => $vehicle->plate,
                'motivo' => $motivo,
            ]);
        } else {
            Log::info('CancelarServicoCacamba: serviço cancelado mas vehicle permanece em manutenção (outras OS ativas)', [
                'business_id' => $businessId,
                'service_order_id' => $serviceOrderId,
                'vehicle_id' => $vehicleId,
                'plate' => $vehicle->plate,
                'motivo' => $motivo,
                'outras_manutencoes_ativas' => $outrasManutencoes,
            ]);
        }
    }
}
