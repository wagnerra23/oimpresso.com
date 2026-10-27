<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-WA-326 — régua de relacionamento por WhatsApp (aniversário e recompra).
 *
 * `whatsapp_reguas_relacionamento`: uma linha por empresa e tipo (`aniversario` | `recompra`), com o
 * texto e, na recompra, quantos dias depois da última compra. Nasce desligada.
 * `whatsapp_regua_envios`: um registro por cliente e ocasião (`referencia`: ano do aniversário ou
 * data da última compra) — rodar de novo no mesmo dia não manda duas vezes.
 * Ambas com business_id (Tier 0, ADR 0093). Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_reguas_relacionamento')) {
            Schema::create('whatsapp_reguas_relacionamento', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id')->index();
                $table->string('tipo', 20)->comment('aniversario|recompra');
                $table->boolean('ativo')->default(false);
                $table->text('texto')->comment('{nome} vira o primeiro nome do cliente');
                $table->unsignedSmallInteger('dias_sem_compra')->nullable()->comment('só recompra');
                $table->timestamps();

                $table->unique(['business_id', 'tipo'], 'wrr_biz_tipo_uq');
                $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('whatsapp_regua_envios')) {
            Schema::create('whatsapp_regua_envios', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id')->index();
                $table->string('tipo', 20);
                $table->unsignedInteger('contact_id');
                $table->string('referencia', 20)->comment('ano do aniversário ou data da última compra');
                $table->string('resultado', 30);
                $table->timestamp('created_at')->nullable();

                $table->unique(['business_id', 'tipo', 'contact_id', 'referencia'], 'wre_biz_tipo_contato_ref_uq');
                $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_regua_envios');
        Schema::dropIfExists('whatsapp_reguas_relacionamento');
    }
};
