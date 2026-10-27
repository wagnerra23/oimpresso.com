<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-AUTO-014 — lembrete de revisão do veículo (decisão [W] 2026-10-26).
 *
 * - `vehicles.next_service_date`: data opcional da próxima revisão (avisa 7 dias antes).
 * - `oficina_lembretes_revisao`: um registro por aviso aceito para envio, para não avisar duas vezes
 *   o mesmo alvo (o km ou a data). Trocar a meta (novo km, nova data) gera um aviso novo.
 *
 * Tier 0 (ADR 0093): `business_id` com índice e FK. Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vehicles') && ! Schema::hasColumn('vehicles', 'next_service_date')) {
            Schema::table('vehicles', function (Blueprint $t) {
                $t->date('next_service_date')->nullable();
            });
        }

        if (! Schema::hasTable('oficina_lembretes_revisao')) {
            Schema::create('oficina_lembretes_revisao', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index('oficina_lembr_rev_biz_idx');
                $t->unsignedBigInteger('vehicle_id');
                $t->enum('tipo', ['km', 'data']);
                $t->string('alvo', 20);
                $t->dateTime('avisado_em');
                $t->timestamps();

                $t->unique(['business_id', 'vehicle_id', 'tipo', 'alvo'], 'oficina_lembr_rev_alvo_uq');
                $t->foreign('business_id', 'oficina_lembr_rev_biz_fk')->references('id')->on('business')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('oficina_lembretes_revisao');
        if (Schema::hasTable('vehicles') && Schema::hasColumn('vehicles', 'next_service_date')) {
            Schema::table('vehicles', fn (Blueprint $t) => $t->dropColumn('next_service_date'));
        }
    }
};
