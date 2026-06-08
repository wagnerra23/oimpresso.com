<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add rental fields to service_orders pra acomodar caso uso "locação de caçamba".
 *
 * Caso piloto Martinho Caçambas (ADR 0137 · amendado ADR 0194 2026-05-26):
 *
 * **Schema preservado nullable.** Pré-ADR 0194 atribuído como fluxo Martinho
 * (locação caçamba container). Pós-correção 2026-05-26: Martinho é sub-vertical 4
 * mecânica pesada caminhão basculante CNAE 4520 (não locação). Schema fica como
 * sub-vertical 3 hipotético sem cliente real ancorado — não dropar até M6+
 * review_trigger.
 *
 * Semântica original (preservada para futuro cliente de locação real):
 * - 1 OS = 1 locação de caçamba container (vai pra cliente, fica X dias, retorna)
 * - daily_rate × dias = valor a receber (cobrança no retorno OU pré-pago)
 * - expected_return_date prometida no momento da locação
 * - delivery_address: endereço pra entrega + recolhimento
 *
 * Campos novos:
 * - order_type           — locacao (sub-vertical 3 hipotético sem cliente) | manutencao (sub-vertical 4 mecânica pesada Martinho LIVE prod default)
 * - delivery_address     — endereço entrega/coleta (texto livre, sem geocoding na V0)
 * - expected_return_date — data prometida de devolução (pra alertar atraso)
 * - daily_rate           — valor diário (pra calcular valor_receber via accessor)
 *
 * `is_overdue` NÃO é coluna — accessor no Model calcula em runtime.
 *
 * Multi-tenant Tier 0 ([ADR 0093]) já preservado via business_id existente.
 *
 * Idempotente.
 *
 * @see memory/decisions/0137-modules-oficinaauto-qualificada.md
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('service_orders', 'order_type')) {
                $table->enum('order_type', ['locacao', 'manutencao'])
                    ->default('manutencao')
                    ->after('vehicle_id')
                    ->comment('Tipo OS — locacao (sub-vertical 3 hipotético container sem cliente real pós-ADR 0194) | manutencao (sub-vertical 4 mecânica pesada Martinho LIVE prod default)');
            }

            if (! Schema::hasColumn('service_orders', 'delivery_address')) {
                $table->string('delivery_address', 255)
                    ->nullable()
                    ->after('order_type')
                    ->comment('Endereço entrega/coleta da caçamba (locação)');
            }

            if (! Schema::hasColumn('service_orders', 'expected_return_date')) {
                $table->date('expected_return_date')
                    ->nullable()
                    ->after('delivery_address')
                    ->comment('Data prometida de devolução — base do alerta is_overdue');
            }

            if (! Schema::hasColumn('service_orders', 'daily_rate')) {
                $table->decimal('daily_rate', 10, 2)
                    ->nullable()
                    ->after('expected_return_date')
                    ->comment('Valor diária locação caçamba (BRL) — multiplicado por dias_locacao');
            }
        });

        // Índice composto pra dashboard "Caçambas locadas hoje" + "Atrasadas":
        // (business_id, order_type) já filtra rapidamente locações ativas.
        Schema::table('service_orders', function (Blueprint $table) {
            $indexes = collect(\DB::select("SHOW INDEX FROM service_orders"))->pluck('Key_name')->unique();
            if (! $indexes->contains('idx_so_business_order_type')) {
                $table->index(['business_id', 'order_type'], 'idx_so_business_order_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $indexes = collect(\DB::select("SHOW INDEX FROM service_orders"))->pluck('Key_name')->unique();
            if ($indexes->contains('idx_so_business_order_type')) {
                $table->dropIndex('idx_so_business_order_type');
            }

            if (Schema::hasColumn('service_orders', 'daily_rate')) {
                $table->dropColumn('daily_rate');
            }
            if (Schema::hasColumn('service_orders', 'expected_return_date')) {
                $table->dropColumn('expected_return_date');
            }
            if (Schema::hasColumn('service_orders', 'delivery_address')) {
                $table->dropColumn('delivery_address');
            }
            if (Schema::hasColumn('service_orders', 'order_type')) {
                $table->dropColumn('order_type');
            }
        });
    }
};
