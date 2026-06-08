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
 * Wave 5-A — Side-effect "Iniciar serviço de manutenção" (process `cacamba_manutencao`).
 *
 * Action `iniciar_servico` (stage `aberta` → `em_servico`).
 *
 * Subject = ServiceOrder (order_type='manutencao'). Garante que Vehicle linkado
 * está com current_status='manutencao' (caso já não esteja — defesa em depth se
 * `enviar_manutencao` não rodou antes):
 *   - vehicle.current_status = 'manutencao' (se ainda não)
 *   - vehicle.current_rental_id permanece (não mexe — manutenção não é rental)
 *
 * Multi-tenant Tier 0 (ADR 0093): valida business_id consistente entre ServiceOrder
 * e Vehicle.
 *
 * Idempotência: chamar 2× resulta no mesmo estado (vehicle.current_status='manutencao').
 *
 * Refs:
 *   - ADR 0143 §FSM Pipeline LIVE prod biz=1
 *   - SPEC.md US-OFICINA-003
 *   - Wave 5-A PR #723 (cadastro action `iniciar_servico`)
 */
final class IniciarServicoCacamba implements SideEffectInterface
{
    public function execute(Model $subject, array $payload = []): void
    {
        if (! $subject instanceof ServiceOrder) {
            throw new InvalidArgumentException(
                'IniciarServicoCacamba: subject deve ser Modules\\OficinaAuto\\Entities\\ServiceOrder (recebido '
                . $subject::class . ')'
            );
        }

        $businessId = (int) $subject->business_id;
        $serviceOrderId = (int) $subject->getKey();
        $vehicleId = (int) $subject->vehicle_id;

        if ($vehicleId <= 0) {
            throw new InvalidArgumentException(
                "IniciarServicoCacamba: ServiceOrder {$serviceOrderId} não tem vehicle_id válido"
            );
        }

        $vehicle = Vehicle::withoutGlobalScope(ScopeByBusiness::class)
            ->where('id', $vehicleId)
            ->where('business_id', $businessId)
            ->first();

        if (! $vehicle) {
            throw new InvalidArgumentException(
                "IniciarServicoCacamba: vehicle {$vehicleId} não encontrado no business {$businessId}"
            );
        }

        if (! Schema::hasColumn('vehicles', 'current_status')) {
            Log::warning('IniciarServicoCacamba: coluna vehicles.current_status ausente — Wave 5-A migration pendente', [
                'business_id' => $businessId,
                'service_order_id' => $serviceOrderId,
                'vehicle_id' => $vehicleId,
            ]);
            return;
        }

        // Garante manutencao — defensivo se enviar_manutencao não rodou antes
        // (cenário possível se OS de manutenção foi criada manualmente sem passar
        // pela action de Vehicle).
        DB::table('vehicles')
            ->where('id', $vehicleId)
            ->where('business_id', $businessId)
            ->update([
                'current_status' => 'manutencao',
                'updated_at' => now(),
            ]);

        Log::info('IniciarServicoCacamba: serviço de manutenção iniciado', [
            'business_id' => $businessId,
            'service_order_id' => $serviceOrderId,
            'vehicle_id' => $vehicleId,
            'plate' => $vehicle->plate,
        ]);
    }
}
