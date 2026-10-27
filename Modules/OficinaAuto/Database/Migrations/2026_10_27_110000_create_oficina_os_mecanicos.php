<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-AUTO-006 — vários mecânicos por OS, com as horas digitadas de cada um (decisão [W] 2026-10-26).
 *
 * Uma linha por mecânico na OS. As horas alimentam a comissão do papel técnico (o cálculo é da frente
 * Vendas · comissão por regra, que lê pelo contrato App\Contracts\Comissao\TecnicosDaOs).
 * O `assigned_user_id` da OS segue como o responsável; esta tabela não o substitui.
 *
 * Tier 0 (ADR 0093): `business_id` com índice e FK. Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('oficina_os_mecanicos')) {
            return;
        }
        Schema::create('oficina_os_mecanicos', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->index('oficina_os_mec_biz_idx');
            $t->unsignedBigInteger('service_order_id');
            $t->unsignedInteger('user_id');
            $t->decimal('horas', 6, 2);
            $t->timestamps();

            $t->unique(['business_id', 'service_order_id', 'user_id'], 'oficina_os_mec_uq');
            $t->foreign('business_id', 'oficina_os_mec_biz_fk')->references('id')->on('business')->cascadeOnDelete();
            $t->foreign('service_order_id', 'oficina_os_mec_os_fk')->references('id')->on('service_orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oficina_os_mecanicos');
    }
};
