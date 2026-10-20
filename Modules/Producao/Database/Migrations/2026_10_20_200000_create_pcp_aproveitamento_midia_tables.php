<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aproveitamento de mídia na bobina (UC-FLX-13 · US-PCP-024).
 *
 * - `pcp_bobinas`: a mídia como a impressora a vê — largura útil da bobina, margem entre peças e se
 *   a peça pode girar. Uma por mídia (`comvis_materiais`) por empresa. O cadastro da mídia é da
 *   Comunicação Visual; a largura útil é do PCP, por isso mora aqui.
 * - `pcp_arranjos` + `pcp_arranjo_os`: o arranjo aceito pelo PCP, com os números do dia (metros,
 *   área útil, perda, o "uma a uma" de comparação) e o consumo previsto de mídia de cada OS (rateio
 *   pela área), ligado ao romaneio gerado para ela. Gravado não muda.
 *
 * Multi-tenant Tier 0 (ADR 0093): `business_id` + FK nas três.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pcp_bobinas')) {
            Schema::create('pcp_bobinas', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id');
                $t->unsignedBigInteger('material_id');
                $t->decimal('largura_util_m', 8, 3);
                $t->decimal('margem_m', 6, 3)->default(0);
                $t->boolean('permite_girar')->default(true);
                $t->timestamps();
                $t->unique(['business_id', 'material_id'], 'pcp_bobinas_biz_material_uq');
                $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('pcp_arranjos')) {
            Schema::create('pcp_arranjos', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id');
                $t->unsignedBigInteger('material_id');
                $t->decimal('largura_util_m', 8, 3);
                $t->decimal('margem_m', 6, 3);
                $t->boolean('permite_girar');
                $t->decimal('metros_lineares', 10, 3);
                $t->decimal('area_util_m2', 10, 3);
                $t->decimal('area_consumida_m2', 10, 3);
                $t->decimal('perda_pct', 6, 2);
                $t->decimal('metros_uma_a_uma', 10, 3);
                $t->unsignedInteger('aceito_por');
                $t->dateTime('aceito_em');
                $t->timestamps();
                $t->index(['business_id', 'material_id'], 'pcp_arranjos_biz_material_idx');
                $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('pcp_arranjo_os')) {
            Schema::create('pcp_arranjo_os', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->foreignId('arranjo_id')->constrained('pcp_arranjos')->cascadeOnDelete();
                $t->string('os_tipo', 30);
                $t->unsignedBigInteger('os_id');
                $t->decimal('area_m2', 10, 3);
                $t->decimal('metros_previstos', 10, 3);
                $t->foreignId('romaneio_id')->nullable()->constrained('pcp_romaneios')->nullOnDelete();
                $t->timestamps();
                $t->index(['business_id', 'os_tipo', 'os_id'], 'pcp_arranjo_os_biz_os_idx');
                $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pcp_arranjo_os');
        Schema::dropIfExists('pcp_arranjos');
        Schema::dropIfExists('pcp_bobinas');
    }
};
