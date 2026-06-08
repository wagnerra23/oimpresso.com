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
 * Wave 5-A — Side-effect "Iniciar locação de caçamba" (process `cacamba_locacao`).
 *
 * Action `iniciar_locacao` (stage `disponivel` → `locada`).
 *
 * Subject = Modules\OficinaAuto\Entities\ServiceOrder (order_type='locacao').
 * Atualiza Vehicle linkado:
 *   - current_status = 'locada'
 *   - current_rental_id = ServiceOrder.id (rastreabilidade da rental ativa)
 *
 * Decisão: SideEffect MUDA APENAS `vehicles.current_status` + `current_rental_id`.
 * Não cria, não fatura, não notifica — single responsibility (Constituição §SoC brutal).
 * Faturamento Asaas (daily_rate × dias) e notificação WhatsApp ficam pra job/observer
 * separado (futuro — escopo Wave 6+).
 *
 * Multi-tenant Tier 0 (ADR 0093): valida que ServiceOrder.business_id == Vehicle.business_id
 * (defesa em depth contra payload spoofing). business_id SEMPRE derivado de subject.
 *
 * Idempotência: UPDATE com WHERE filtra estado anterior — chamar 2× resulta no mesmo
 * estado final (vehicle.current_status='locada', current_rental_id=ServiceOrder.id).
 * Trust FSM gate (transição disponivel→locada bloqueia rerun via GuardsFsmTransitions),
 * mas defensivo se Wave 5-A migration não aplicada (Schema::hasColumn guard).
 *
 * Refs:
 *   - ADR 0143 §FSM Pipeline LIVE prod biz=1
 *   - ADR 0137 §Modules/OficinaAuto qualificada
 *   - SPEC.md US-OFICINA-003 (FSM Pipeline OficinaAuto)
 *   - Wave 5-A PR #723 (cadastro action `iniciar_locacao`)
 */
final class IniciarLocacaoCacamba implements SideEffectInterface
{
    public function execute(Model $subject, array $payload = []): void
    {
        if (! $subject instanceof ServiceOrder) {
            throw new InvalidArgumentException(
                'IniciarLocacaoCacamba: subject deve ser Modules\\OficinaAuto\\Entities\\ServiceOrder (recebido '
                . $subject::class . ')'
            );
        }

        $businessId = (int) $subject->business_id;
        $serviceOrderId = (int) $subject->getKey();
        $vehicleId = (int) $subject->vehicle_id;

        if ($vehicleId <= 0) {
            throw new InvalidArgumentException(
                "IniciarLocacaoCacamba: ServiceOrder {$serviceOrderId} não tem vehicle_id válido"
            );
        }

        // Tier 0: defesa contra cross-tenant via vehicle órfão / payload spoof
        $vehicle = Vehicle::withoutGlobalScope(ScopeByBusiness::class)
            ->where('id', $vehicleId)
            ->where('business_id', $businessId)
            ->first();

        if (! $vehicle) {
            throw new InvalidArgumentException(
                "IniciarLocacaoCacamba: vehicle {$vehicleId} não encontrado no business {$businessId} "
                . '(possível cross-tenant ou vehicle deletado)'
            );
        }

        // Schema guard: se Wave 5-A migration ainda não rodou, log warning e aborta sem crash.
        // Em prod biz=1 com migration aplicada, segue normal.
        if (! Schema::hasColumn('vehicles', 'current_status')) {
            Log::warning('IniciarLocacaoCacamba: coluna vehicles.current_status ausente — Wave 5-A migration pendente', [
                'business_id' => $businessId,
                'service_order_id' => $serviceOrderId,
                'vehicle_id' => $vehicleId,
            ]);
            return;
        }

        // UPDATE direto via DB::table evita dependência de $fillable em Vehicle Model
        // (Wave 5-A adiciona current_status/current_rental_id ao $fillable + casts).
        DB::table('vehicles')
            ->where('id', $vehicleId)
            ->where('business_id', $businessId) // Tier 0 — double-check no SQL
            ->update([
                'current_status' => 'locada',
                'current_rental_id' => $serviceOrderId,
                'updated_at' => now(),
            ]);

        Log::info('IniciarLocacaoCacamba: caçamba marcada como locada', [
            'business_id' => $businessId,
            'service_order_id' => $serviceOrderId,
            'vehicle_id' => $vehicleId,
            'plate' => $vehicle->plate,
        ]);
    }
}
