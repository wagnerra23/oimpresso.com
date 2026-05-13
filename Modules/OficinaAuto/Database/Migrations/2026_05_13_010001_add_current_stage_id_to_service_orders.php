<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 7 emergência — adiciona current_stage_id em service_orders.
 *
 * Wave 5-A (PR #723) esqueceu essa coluna. Wave 7-A
 * (ServiceOrderFsmActionController) depende dela pra rastrear stage atual da
 * OS no pipeline FSM (cacamba_locacao OU cacamba_manutencao).
 *
 * Sem essa coluna o FSM gateway (ExecuteStageActionService) não tem onde
 * gravar transições. Fail-soft em controller via Schema::hasColumn quando
 * coluna ausente.
 *
 * Refs: ADR 0143 (FSM Pipeline LIVE) · PR #723 Wave 5-A · PRs #725-#727 Wave 6
 *       · Wave 7 backend + frontend Drawer FSM ServiceOrder
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_orders')) {
            return;
        }

        if (Schema::hasColumn('service_orders', 'current_stage_id')) {
            return;
        }

        Schema::table('service_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('current_stage_id')
                ->nullable()
                ->after('status')
                ->comment('FSM stage atual (FK lógica sale_process_stages — sem FK física pra evitar cascade)');

            $table->index(
                ['business_id', 'current_stage_id'],
                'idx_service_orders_business_current_stage'
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('service_orders') || ! Schema::hasColumn('service_orders', 'current_stage_id')) {
            return;
        }

        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropIndex('idx_service_orders_business_current_stage');
            $table->dropColumn('current_stage_id');
        });
    }
};
