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
use Modules\Jana\Scopes\ScopeByBusiness;

/**
 * Wave 5-A — Side-effect "Enviar caçamba pra manutenção" (process `cacamba_locacao`).
 *
 * Action `enviar_manutencao` (qualquer stage → `manutencao`, marcada `is_critical=true`).
 *
 * Subject = Modules\OficinaAuto\Entities\Vehicle (mudança DIRETA de status — não passa
 * por uma rental ativa). Atualiza:
 *   - current_status = 'manutencao'
 *   - current_rental_id = null (libera referência se houver rental)
 *
 * Decisão arquitetural: SideEffect NÃO cria ServiceOrder de manutenção
 * automaticamente. Razão (Constituição §SoC brutal):
 *   1. Single responsibility — SideEffect só atualiza estado.
 *   2. OS de manutenção tem campos próprios (mileage_at_service, expected_completion,
 *      notes operacionais) que precisam de UI ou input do usuário, não dá pra
 *      gerar com defaults seguros.
 *   3. Audit log já existe via sale_stage_history (FSM canon — append-only).
 *   4. Se Wagner quiser auto-criar OS, vira job/observer em Wave 6+ (passar
 *      payload['create_service_order' => true] como flag opt-in).
 *
 * Multi-tenant Tier 0 (ADR 0093): subject->business_id derivado direto, sem payload.
 *
 * Idempotência: UPDATE incondicional resulta em mesmo state se já manutencao.
 *
 * Refs:
 *   - ADR 0143 §FSM Pipeline LIVE prod biz=1
 *   - ADR 0094 §SoC brutal (Constituição v2 §5)
 *   - SPEC.md US-OFICINA-003
 *   - Wave 5-A PR #723 (cadastro action `enviar_manutencao` is_critical=true)
 */
final class EnviarCacambaManutencao implements SideEffectInterface
{
    public function execute(Model $subject, array $payload = []): void
    {
        if (! $subject instanceof Vehicle) {
            throw new InvalidArgumentException(
                'EnviarCacambaManutencao: subject deve ser Modules\\OficinaAuto\\Entities\\Vehicle (recebido '
                . $subject::class . ')'
            );
        }

        $businessId = (int) $subject->business_id;
        $vehicleId = (int) $subject->getKey();
        $motivo = (string) ($payload['motivo'] ?? 'Envio pra manutenção via FSM');

        if (! Schema::hasColumn('vehicles', 'current_status')) {
            Log::warning('EnviarCacambaManutencao: coluna vehicles.current_status ausente — Wave 5-A migration pendente', [
                'business_id' => $businessId,
                'vehicle_id' => $vehicleId,
            ]);
            return;
        }

        // Tier 0: WHERE business_id explícito como defesa em SQL
        DB::table('vehicles')
            ->where('id', $vehicleId)
            ->where('business_id', $businessId)
            ->update([
                'current_status' => 'manutencao',
                'current_rental_id' => null,
                'updated_at' => now(),
            ]);

        Log::info('EnviarCacambaManutencao: caçamba enviada pra manutenção', [
            'business_id' => $businessId,
            'vehicle_id' => $vehicleId,
            'plate' => $subject->plate,
            'motivo' => $motivo,
        ]);
    }
}
