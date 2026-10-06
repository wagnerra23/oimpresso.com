<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agenda de revisão da Oficina (decisão [W] 2026-10-06): veículo + cliente sugerido + dia e hora +
 * observação curta. Na chegada, "Abrir OS" cria a OS e o agendamento fica `atendido`, ligado a ela.
 *
 * Tabela PRÓPRIA da Oficina: o `bookings` do core é reserva de restaurante (sem veículo, depende do
 * módulo 'booking') e não é reutilizado.
 *
 * Multi-tenant Tier 0 (ADR 0093): business_id indexado + FK. Sem valor, estoque ou cobrança.
 * Aditiva e idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('oficina_agendamentos')) {
            return;
        }

        Schema::create('oficina_agendamentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('vehicle_id');
            $table->unsignedInteger('contact_id')->nullable();
            $table->dateTime('inicio');
            $table->string('observacao', 500)->nullable();
            $table->string('status', 20)->default('agendado')->comment('agendado | atendido | cancelado');
            $table->unsignedBigInteger('os_id')->nullable()->comment('service_orders.id quando atendido');
            $table->string('motivo_cancelamento', 500)->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'inicio'], 'oficina_agend_biz_inicio_idx');
            $table->index(['business_id', 'vehicle_id'], 'oficina_agend_biz_vehicle_idx');
            $table->foreign('business_id', 'oficina_agend_business_fk')
                ->references('id')->on('business')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oficina_agendamentos');
    }
};
