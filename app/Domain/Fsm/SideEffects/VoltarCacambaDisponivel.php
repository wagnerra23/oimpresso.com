<?php

declare(strict_types=1);

namespace App\Domain\Fsm\SideEffects;

use App\Domain\Fsm\Contracts\SideEffectInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Modules\OficinaAuto\Entities\Vehicle;

/**
 * Wave 5-A — Side-effect "Voltar caçamba pra disponível" (process `cacamba_locacao`).
 *
 * Action `voltar_disponivel` (stages `manutencao`/`recolhida` → `disponivel`).
 *
 * Subject = Modules\OficinaAuto\Entities\Vehicle. Atualiza:
 *   - current_status = 'disponivel'
 *   - current_rental_id = null
 *
 * Decisão: SideEffect só atualiza estado do Vehicle. Não cria, não fatura, não notifica.
 * Single responsibility (Constituição §SoC brutal).
 *
 * Multi-tenant Tier 0 (ADR 0093): subject->business_id direto.
 *
 * Idempotência: UPDATE com WHERE business_id — chamar 2× resulta no mesmo estado final.
 *
 * Refs:
 *   - ADR 0143 §FSM Pipeline LIVE prod biz=1
 *   - SPEC.md US-OFICINA-003
 *   - Wave 5-A PR #723 (cadastro action `voltar_disponivel`)
 */
final class VoltarCacambaDisponivel implements SideEffectInterface
{
    public function execute(Model $subject, array $payload = []): void
    {
        if (! $subject instanceof Vehicle) {
            throw new InvalidArgumentException(
                'VoltarCacambaDisponivel: subject deve ser Modules\\OficinaAuto\\Entities\\Vehicle (recebido '
                . $subject::class . ')'
            );
        }

        $businessId = (int) $subject->business_id;
        $vehicleId = (int) $subject->getKey();

        if (! Schema::hasColumn('vehicles', 'current_status')) {
            Log::warning('VoltarCacambaDisponivel: coluna vehicles.current_status ausente — Wave 5-A migration pendente', [
                'business_id' => $businessId,
                'vehicle_id' => $vehicleId,
            ]);
            return;
        }

        DB::table('vehicles')
            ->where('id', $vehicleId)
            ->where('business_id', $businessId) // Tier 0 SQL guard
            ->update([
                'current_status' => 'disponivel',
                'current_rental_id' => null,
                'updated_at' => now(),
            ]);

        Log::info('VoltarCacambaDisponivel: caçamba devolvida pro pool disponível', [
            'business_id' => $businessId,
            'vehicle_id' => $vehicleId,
            'plate' => $subject->plate,
        ]);
    }
}
