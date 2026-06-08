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
 * Wave 5-A — Side-effect "Recolher caçamba" (process `cacamba_locacao`).
 *
 * Action `recolher` (stage `locada` → `recolhida`).
 *
 * Subject = ServiceOrder. Marca Vehicle linkado como disponível novamente:
 *   - current_status = 'disponivel'
 *   - current_rental_id = null (libera a referência da rental encerrada)
 *
 * Decisão: NÃO fatura, NÃO notifica WhatsApp/email (LGPD opt-in fica em job futuro).
 * Single responsibility — só atualiza estado do Vehicle.
 *
 * Multi-tenant Tier 0 (ADR 0093): subject->business_id === vehicle->business_id (validate).
 *
 * Idempotência: UPDATE com WHERE filtra business_id — chamar 2× resulta no mesmo estado
 * (vehicle.current_status='disponivel', current_rental_id=null).
 *
 * Refs:
 *   - ADR 0143 §FSM Pipeline LIVE prod biz=1
 *   - SPEC.md US-OFICINA-003
 *   - Wave 5-A PR #723 (cadastro action `recolher`)
 */
final class RecolherCacamba implements SideEffectInterface
{
    public function execute(Model $subject, array $payload = []): void
    {
        if (! $subject instanceof ServiceOrder) {
            throw new InvalidArgumentException(
                'RecolherCacamba: subject deve ser Modules\\OficinaAuto\\Entities\\ServiceOrder (recebido '
                . $subject::class . ')'
            );
        }

        $businessId = (int) $subject->business_id;
        $serviceOrderId = (int) $subject->getKey();
        $vehicleId = (int) $subject->vehicle_id;

        if ($vehicleId <= 0) {
            throw new InvalidArgumentException(
                "RecolherCacamba: ServiceOrder {$serviceOrderId} não tem vehicle_id válido"
            );
        }

        $vehicle = Vehicle::withoutGlobalScope(ScopeByBusiness::class)
            ->where('id', $vehicleId)
            ->where('business_id', $businessId)
            ->first();

        if (! $vehicle) {
            throw new InvalidArgumentException(
                "RecolherCacamba: vehicle {$vehicleId} não encontrado no business {$businessId}"
            );
        }

        if (! Schema::hasColumn('vehicles', 'current_status')) {
            Log::warning('RecolherCacamba: coluna vehicles.current_status ausente — Wave 5-A migration pendente', [
                'business_id' => $businessId,
                'service_order_id' => $serviceOrderId,
                'vehicle_id' => $vehicleId,
            ]);
            return;
        }

        DB::table('vehicles')
            ->where('id', $vehicleId)
            ->where('business_id', $businessId)
            ->update([
                'current_status' => 'disponivel',
                'current_rental_id' => null,
                'updated_at' => now(),
            ]);

        Log::info('RecolherCacamba: caçamba recolhida e disponibilizada', [
            'business_id' => $businessId,
            'service_order_id' => $serviceOrderId,
            'vehicle_id' => $vehicleId,
            'plate' => $vehicle->plate,
        ]);
    }
}
