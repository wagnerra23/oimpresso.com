<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Database\Seeders\OficinaAutoFsmSeeder;

/**
 * Garante o processo FSM `oficina_mecanica_os` nos businesses que JÁ usam a Oficina
 * (ADR 0265 — fio usável ponta a ponta).
 *
 * Evidência prod 2026-06-10 (SELECT read-only Hostinger): biz=1 e biz=164 (Martinho)
 * só têm `cacamba_locacao`/`cacamba_manutencao` — o processo de mecânica (seeder
 * seedMecanicaOsProcess, 2026-06-02) NUNCA rodou em prod porque deploy executa
 * `migrate --force` mas não `db:seed`. Sem ele:
 *  - o auto-start do ServiceOrderController::store() falha (OS nasce fora de pipeline);
 *  - a migration irmã 2026_06_10_000001 (repoint de órfãs) não acha o stage `recepcao`.
 *
 * Roda o seeder canônico (idempotente — firstOrCreate em processo/stage/action/role)
 * APENAS pros businesses que já têm processo cacamba_* ou oficina_* OU service_orders —
 * não semeia FSM da Oficina em tenant que nunca usou o módulo.
 *
 * down(): no-op — remover processo FSM em uso quebraria OS vivas (append-only ADR 0143).
 *
 * @see Modules/OficinaAuto/Database/Seeders/OficinaAutoFsmSeeder.php
 * @see memory/decisions/0265-oficina-reparo-erradica-locacao.md
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_orders')
            || ! Schema::hasTable('sale_processes')
            || ! Schema::hasTable('roles')
        ) {
            return;
        }

        $businessIds = DB::table('sale_processes')
            ->whereIn('key', ['cacamba_locacao', 'cacamba_manutencao', 'oficina_mecanica_os'])
            ->pluck('business_id')
            ->merge(DB::table('service_orders')->distinct()->pluck('business_id'))
            ->unique()
            ->filter()
            ->values();

        if ($businessIds->isEmpty()) {
            return;
        }

        $seeder = new OficinaAutoFsmSeeder();
        foreach ($businessIds as $businessId) {
            $seeder->runForBusiness((int) $businessId);
        }
    }

    public function down(): void
    {
        // No-op: remover processo FSM em uso orfanaria current_stage_id de OS vivas
        // (exatamente a classe de bug que a ADR 0265 está fechando). Append-only.
    }
};
