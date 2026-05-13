<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add cacamba avulsa estacionária fields to vehicles.
 *
 * Caso piloto Martinho Caçambas (ADR 0137 §"Sub-vertical Caçamba Estacionária"):
 * - 91 caminhões/caçambas + 44.709 vendas históricas Firebird
 * - Modelo de negócio: locação de caçamba pra entulho/obra (não oficina automotiva pura)
 * - PLACA 95.6% sem reboque — perfil simples
 *
 * Campos novos (todos nullable pra back-compat com OS automotiva existente):
 * - capacity_m3        — capacidade da caçamba (3.00, 5.00, 7.00 m³)
 * - vehicle_type       — ENUM 4 valores específicos do sub-vertical caçamba
 *                        (substitui ENUM original mais amplo via UPDATE em column existente)
 * - current_status     — disponivel | locada | manutencao | indisponivel
 *                        (denormalizado do estado FSM canônico pra query rápida em listagem)
 * - current_rental_id  — FK SOFT pra service_orders.id quando current_status='locada'
 *                        (sem constraint FK pra evitar cascade nightmares quando OS é deletada)
 *
 * Multi-tenant Tier 0 ([ADR 0093]): índice composto (business_id, current_status)
 * pra dashboard "Caçambas disponíveis hoje" performar.
 *
 * Idempotente (Schema::hasColumn guard).
 *
 * @see memory/decisions/0137-modules-oficinaauto-qualificada.md
 * @see memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            if (! Schema::hasColumn('vehicles', 'capacity_m3')) {
                $table->decimal('capacity_m3', 5, 2)
                    ->nullable()
                    ->after('vehicle_type')
                    ->comment('Capacidade da caçamba em m³ (3.00, 5.00, 7.00 — caso Martinho)');
            }

            if (! Schema::hasColumn('vehicles', 'current_status')) {
                $table->enum('current_status', [
                    'disponivel',
                    'locada',
                    'manutencao',
                    'indisponivel',
                ])
                    ->default('disponivel')
                    ->after('capacity_m3')
                    ->comment('Estado denormalizado da caçamba — sincronizado com FSM via side-effect');
            }

            if (! Schema::hasColumn('vehicles', 'current_rental_id')) {
                $table->unsignedBigInteger('current_rental_id')
                    ->nullable()
                    ->after('current_status')
                    ->comment('FK soft pra service_orders.id quando locada (evita join nested em listagem)');
            }
        });

        // ENUM vehicle_type — adicionar valores caçamba sem perder os existentes.
        // Estratégia: ALTER nativo MySQL pra incluir 4 valores novos preservando os 7 originais.
        // (back-compat com tests `cacamba_estacionaria` + `automovel` etc).
        if (Schema::hasColumn('vehicles', 'vehicle_type')) {
            \DB::statement("
                ALTER TABLE vehicles MODIFY COLUMN vehicle_type ENUM(
                    'caminhao',
                    'cavalo',
                    'semi_reboque',
                    'cacamba_estacionaria',
                    'cacamba_avulsa',
                    'cacamba_caminhao',
                    'recapagem',
                    'automovel',
                    'motocicleta',
                    'outros',
                    'outro'
                ) NOT NULL DEFAULT 'cacamba_avulsa'
                COMMENT 'Tipo do veículo — ENUM expandido pra acomodar sub-vertical caçamba (Martinho)'
            ");
        }

        // Índice composto pra dashboard "caçambas disponíveis" — Tier 0 multi-tenant.
        Schema::table('vehicles', function (Blueprint $table) {
            $indexes = collect(\DB::select("SHOW INDEX FROM vehicles"))->pluck('Key_name')->unique();
            if (! $indexes->contains('idx_vehicles_business_status')) {
                $table->index(['business_id', 'current_status'], 'idx_vehicles_business_status');
            }
            if (! $indexes->contains('idx_vehicles_current_rental')) {
                $table->index('current_rental_id', 'idx_vehicles_current_rental');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $indexes = collect(\DB::select("SHOW INDEX FROM vehicles"))->pluck('Key_name')->unique();
            if ($indexes->contains('idx_vehicles_business_status')) {
                $table->dropIndex('idx_vehicles_business_status');
            }
            if ($indexes->contains('idx_vehicles_current_rental')) {
                $table->dropIndex('idx_vehicles_current_rental');
            }

            if (Schema::hasColumn('vehicles', 'current_rental_id')) {
                $table->dropColumn('current_rental_id');
            }
            if (Schema::hasColumn('vehicles', 'current_status')) {
                $table->dropColumn('current_status');
            }
            if (Schema::hasColumn('vehicles', 'capacity_m3')) {
                $table->dropColumn('capacity_m3');
            }
        });

        // Reverter ENUM pro original (apenas se safe — nenhuma row usando valor novo).
        if (Schema::hasColumn('vehicles', 'vehicle_type')) {
            \DB::statement("
                ALTER TABLE vehicles MODIFY COLUMN vehicle_type ENUM(
                    'caminhao',
                    'cavalo',
                    'semi_reboque',
                    'cacamba_estacionaria',
                    'automovel',
                    'motocicleta',
                    'outro'
                ) NOT NULL DEFAULT 'automovel'
            ");
        }
    }
};
