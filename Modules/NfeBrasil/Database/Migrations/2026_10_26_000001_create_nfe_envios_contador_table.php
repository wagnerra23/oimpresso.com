<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-NFE-073 · histórico do envio mensal dos XML ao contador.
 *
 * Uma linha por empresa × competência (AAAA-MM). O índice único é o que deixa o envio automático
 * idempotente: rodar o comando duas vezes no mesmo mês não manda dois e-mails. `business_id` + FK
 * (ADR 0093). Idempotente (Schema::hasTable).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nfe_envios_contador')) {
            return;
        }

        Schema::create('nfe_envios_contador', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->char('competencia', 7)->comment('AAAA-MM');
            $table->string('email', 191);
            $table->unsignedInteger('qtd_xml')->default(0);
            $table->unsignedInteger('qtd_sem_xml')->default(0)->comment('notas do mês cujo XML não foi achado');
            $table->unsignedBigInteger('bytes')->default(0);
            $table->string('status', 20)->comment('enviado | falha');
            $table->string('erro', 500)->nullable();
            $table->timestamp('enviado_em')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'competencia'], 'nfe_envios_contador_biz_comp_uq');
            $table->foreign('business_id', 'nfe_envios_contador_business_fk')
                ->references('id')->on('business')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfe_envios_contador');
    }
};
