<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Playbook Fiscal thread 15a · revisão do contador (D-SUPORTE · D-CONTADOR).
 *
 * Uma linha por versão de regra que o contador precisa conferir: o que mudou (de → para), quem
 * mudou, de onde veio (manual · csv · jana · template) e, quando aceita, quem aceitou e de onde.
 * O aceite fica AQUI, e não em coluna da regra: a regra é append-only (thread 07), e aceitar não
 * é editar. Editar uma regra aceita cria versão nova → revisão nova, pendente; a antiga mantém o
 * aceite dela.
 *
 * Só cria tabela — nenhuma tabela existente muda. `business_id` + FK (ADR 0093).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nfe_revisoes_contador')) {
            return;
        }

        Schema::create('nfe_revisoes_contador', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('regra_id');
            $table->unsignedBigInteger('regra_anterior_id')->nullable();
            $table->string('origem', 20)->default('manual')->comment('manual · csv · jana · template');
            $table->unsignedInteger('autor_id')->nullable();
            $table->json('diff');
            // Enum declarado em memory/dominio/fiscal-faturamento.md (dominio:check, ADR 0264 G-4).
            $table->enum('status', ['pendente', 'aceita', 'ajuste_pedido'])->default('pendente');
            $table->text('comentario')->nullable();
            $table->string('aceito_por_nome', 191)->nullable();
            $table->string('aceito_por_email', 191)->nullable();
            $table->string('aceito_por_crc', 40)->nullable();
            $table->unsignedInteger('aceito_por_user_id')->nullable();
            $table->timestamp('aceito_em')->nullable();
            $table->string('aceito_ip', 45)->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status'], 'nfe_revisoes_contador_biz_status_idx');
            $table->index('regra_id', 'nfe_revisoes_contador_regra_idx');
            $table->foreign('business_id', 'nfe_revisoes_contador_business_fk')
                ->references('id')->on('business')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfe_revisoes_contador');
    }
};
