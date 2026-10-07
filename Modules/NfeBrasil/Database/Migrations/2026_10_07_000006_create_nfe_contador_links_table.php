<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Playbook Fiscal thread 15b · link de revisão do contador (D-CONTADOR, caminho 1).
 *
 * Um link por envio: preso a UM business, vale 14 dias (US-NFE-009) e só abre com o código de
 * 6 dígitos mandado ao mesmo e-mail (15 min; 5 erros bloqueiam o link). O código fica só como
 * hash e é apagado ao ser usado, para não servir duas vezes. O nome, e-mail e CRC daqui são os
 * que o aceite pelo link grava em `nfe_revisoes_contador`.
 *
 * Só cria tabela. `business_id` + FK (ADR 0093).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nfe_contador_links')) {
            return;
        }

        Schema::create('nfe_contador_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->string('nome', 191);
            $table->string('email', 191);
            $table->string('crc', 40)->nullable();
            $table->string('codigo_hash', 255)->nullable();
            $table->timestamp('codigo_expira_em')->nullable();
            $table->unsignedTinyInteger('tentativas')->default(0);
            $table->timestamp('bloqueado_em')->nullable();
            $table->timestamp('expira_em');
            $table->unsignedInteger('criado_por')->nullable();
            $table->timestamps();

            $table->index('business_id', 'nfe_contador_links_biz_idx');
            $table->foreign('business_id', 'nfe_contador_links_business_fk')
                ->references('id')->on('business')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfe_contador_links');
    }
};
