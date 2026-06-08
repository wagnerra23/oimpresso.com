<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 2.1 US-OFICINA-027 — Adiciona `box_label` + `assigned_user_id` em service_orders.
 *
 * Origem: drawer Cowork canon (`prototipo-ui/_BACKUP-NAO-USAR/.../os/cowork-app.jsx` +
 * screenshot Wagner 2026-05-26) renderiza KV grid pro modo manutenção com:
 *   - Box (ex "Elevador 1") → `box_label` string livre (MVP — sem tabela boxes ainda;
 *     baixo cardinality e sem sinal qualificado pra criar entity nova ADR 0105)
 *   - Mecânico (ex "Pedro Souza") → `assigned_user_id` FK lógica `users.id`
 *
 * Sem FK física (pattern Wave 5-A current_rental_id e Wave 7 current_stage_id) pra
 * evitar cascade nightmares quando user é deletado/desativado — Service layer valida
 * existência.
 *
 * Multi-tenant Tier 0 [ADR 0093]: business_id continua scope mãe; box_label é texto
 * livre per-business (sem leak); assigned_user_id resolvido via global scope users
 * (cada biz tem seus mecânicos).
 *
 * Idempotente (pattern padrão módulo Wave 7+).
 *
 * @see resources/js/Pages/OficinaAuto/ProducaoOficina/_components/CacambaProducaoSheet.tsx (Wave 2.2 vira ServiceOrderRichSheet polimórfico)
 * @see memory/requisitos/OficinaAuto/SPEC.md US-OFICINA-027
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_orders')) {
            return;
        }

        Schema::table('service_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('service_orders', 'box_label')) {
                $table->string('box_label', 50)
                    ->nullable()
                    ->after('mileage_at_service')
                    ->comment('Box físico onde OS está sendo executada (ex "Elevador 1"). MVP texto livre — sem tabela boxes até sinal qualificado ADR 0105.');
            }

            if (! Schema::hasColumn('service_orders', 'assigned_user_id')) {
                $table->unsignedInteger('assigned_user_id')
                    ->nullable()
                    ->after('box_label')
                    ->comment('FK lógica users.id — mecânico responsável pela OS. Sem FK física (pattern Wave 5-A).');

                $table->index(['business_id', 'assigned_user_id'], 'idx_service_orders_business_assigned_user');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('service_orders')) {
            return;
        }

        Schema::table('service_orders', function (Blueprint $table) {
            if (Schema::hasColumn('service_orders', 'assigned_user_id')) {
                $table->dropIndex('idx_service_orders_business_assigned_user');
                $table->dropColumn('assigned_user_id');
            }

            if (Schema::hasColumn('service_orders', 'box_label')) {
                $table->dropColumn('box_label');
            }
        });
    }
};
