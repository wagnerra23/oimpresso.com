<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration service_orders — ordens de serviço da oficina.
 *
 * Schema definido em ADR 0137 §"Escopo arquitetural V0":
 * - 1 OS = 1 venda (transaction_id FK pra transactions UltimatePOS, nullable durante draft)
 * - mileage_at_service: KM no momento da entrada (auditoria + lembrete revisão futura)
 * - status: string livre na V0; FSM canônica entra em US-OFICINA-003 ([ADR 0129])
 *   - OS Simples (Martinho): aberta → em_servico → concluida
 *   - OS Complexa (Vargas, V1): aberta → orcamento → aprovada → em_producao → concluida → entregue
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * business_id indexado + FK CASCADE.
 *
 * @see memory/requisitos/OficinaAuto/SPEC.md US-OFICINA-001
 * @see memory/decisions/0137-modules-oficinaauto-qualificada.md
 * @see memory/decisions/0129-state-machine-canonica-fsm-rbac.md
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            // int(10) unsigned pra bater com business.id (UltimatePOS legacy schema — FK constraint)
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('transaction_id')->nullable();  // FK transactions (UltimatePOS) — 1 OS = 1 venda
            $table->unsignedBigInteger('vehicle_id');
            $table->integer('mileage_at_service')->unsigned()->nullable();
            $table->string('status', 30)->default('aberta');           // FSM gerencia transições (US-OFICINA-003)
            $table->timestamp('entered_at')->nullable();
            $table->timestamp('expected_completion')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'status'], 'idx_so_business_status');
            $table->index(['business_id', 'vehicle_id'], 'idx_so_business_vehicle');
            $table->index(['business_id', 'transaction_id'], 'idx_so_business_transaction');

            $table->foreign('business_id', 'fk_so_business')
                  ->references('id')->on('business')->cascadeOnDelete();
            $table->foreign('vehicle_id', 'fk_so_vehicle')
                  ->references('id')->on('vehicles')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_orders');
    }
};
