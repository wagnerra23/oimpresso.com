<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Database\Seeders;

use App\Business;
use Illuminate\Database\Seeder;
use Modules\OficinaAuto\Entities\OaInspectionItem;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\ServiceOrderItem;
use Modules\OficinaAuto\Entities\Vehicle;

/**
 * Seeder de DEMO LIMPA do documento-vivo OficinaAuto (worklist TRAVA-SEGUNDA · CU-6).
 *
 * Cria um starting-point reproduzível pra o "wow" da Kamila: 1 veículo + 1 OS
 * em `aberta` (check-in) + itens (peça/mão-obra) + DVI (inspeção) — pronto pra
 * percorrer check-in → DVI → aprovação → execução SEM depender dos dados de prod
 * (biz=164 tem os 91 veículos reais; este seeder dá demo isolada em qualquer biz).
 *
 * **NÃO** entra no `OficinaAutoDatabaseSeeder` (não auto-semeia em todo migrate) —
 * roda explícito:
 *   php artisan db:seed --class="Modules\\OficinaAuto\\Database\\Seeders\\OficinaAutoDemoSeeder"
 *
 * Idempotente (firstOrCreate por placa demo) — re-rodar não duplica.
 *
 * Business alvo: env `OFICINA_DEMO_BUSINESS_ID` (ex 164 p/ Martinho) OU o primeiro
 * business do banco (dev). Multi-tenant Tier 0: tudo carimbado com business_id (ADR 0093).
 *
 * @see memory/decisions/0171-oficinaauto-ativacao-piloto-martinho-faseada.md
 * @see memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md
 */
class OficinaAutoDemoSeeder extends Seeder
{
    /** Placa demo reconhecível (Mercosul) — chave de idempotência. */
    public const DEMO_PLATE = 'DEM0A11';

    public function run(): void
    {
        $businessId = $this->resolveBusinessId();
        if ($businessId === null) {
            $this->command?->warn('[OficinaAutoDemo] Nenhum business encontrado — pulei (rode o seed base do UltimatePOS antes).');

            return;
        }

        $vehicle = Vehicle::withoutGlobalScopes()->firstOrCreate(
            ['business_id' => $businessId, 'plate' => self::DEMO_PLATE],
            [
                'vehicle_type'     => 'caminhao',
                'capacity_m3'      => 12,
                'color'            => 'Branca',
                'manufacture_year' => 2019,
                'notes'            => 'Veículo de DEMO OficinaAuto (worklist TRAVA-SEGUNDA).',
            ],
        );

        $os = ServiceOrder::withoutGlobalScopes()->firstOrCreate(
            ['business_id' => $businessId, 'vehicle_id' => $vehicle->id, 'status' => 'aberta'],
            [
                'order_type' => 'manutencao',
                'box_label'  => 'Box 1',
                'entered_at' => now(),
                'notes'      => 'OS DEMO — revisão de freios + troca de pastilhas dianteiras.',
            ],
        );

        // Itens do documento-vivo (peça + mão-de-obra).
        ServiceOrderItem::withoutGlobalScopes()->firstOrCreate(
            ['business_id' => $businessId, 'service_order_id' => $os->id, 'tipo' => 'peca', 'descricao' => 'Pastilha de freio dianteira (par)'],
            ['quantidade' => 1, 'valor_unitario' => 180.00, 'valor_total' => 180.00],
        );
        ServiceOrderItem::withoutGlobalScopes()->firstOrCreate(
            ['business_id' => $businessId, 'service_order_id' => $os->id, 'tipo' => 'mao_obra', 'descricao' => 'Mão de obra — troca de pastilhas + sangria'],
            ['quantidade' => 1, 'valor_unitario' => 120.00, 'valor_total' => 120.00],
        );

        // DVI — inspeção visual (o diferencial vertical do "documento-vivo").
        OaInspectionItem::withoutGlobalScopes()->firstOrCreate(
            ['business_id' => $businessId, 'service_order_id' => $os->id, 'categoria' => 'freios'],
            [
                'descricao'         => 'Pastilhas dianteiras com ~25% de vida útil.',
                'severity'          => 'atencao',
                'recomendacao'      => 'Substituir pastilhas dianteiras.',
                'valor_recomendado' => 180.00,
                'sort_order'        => 1,
            ],
        );
        OaInspectionItem::withoutGlobalScopes()->firstOrCreate(
            ['business_id' => $businessId, 'service_order_id' => $os->id, 'categoria' => 'pneus'],
            [
                'descricao'  => 'Pneus dianteiros em bom estado.',
                'severity'   => 'ok',
                'sort_order' => 2,
            ],
        );

        $this->command?->info("[OficinaAutoDemo] OK · business_id={$businessId} · OS #{$os->id} (veículo {$vehicle->plate}) pronta pra demo check-in→DVI→aprovação→execução.");
    }

    /** Business alvo: env explícito (ex 164 Martinho) ou o primeiro do banco. */
    private function resolveBusinessId(): ?int
    {
        $fromEnv = env('OFICINA_DEMO_BUSINESS_ID');
        if (! empty($fromEnv)) {
            return (int) $fromEnv;
        }

        return Business::query()->orderBy('id')->value('id');
    }
}
