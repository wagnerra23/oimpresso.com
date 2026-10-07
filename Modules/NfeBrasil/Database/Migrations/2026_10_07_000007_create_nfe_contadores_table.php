<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Playbook Fiscal thread 15c · cadastro do contador da empresa (D-CONTADOR).
 *
 * Um contador por business: nome, e-mail e CRC, editados em `/fiscal/config` › Envio de documentos.
 * Antes não havia onde guardar isso (o card dizia "ainda não existe campo"). Tabela própria em vez
 * de colunas em `nfe_business_configs`: nenhuma tabela existente muda, e o rollback é limpo.
 *
 * `business_id` único + FK (ADR 0093).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nfe_contadores')) {
            return;
        }

        Schema::create('nfe_contadores', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->unique('nfe_contadores_business_uq');
            $table->string('nome', 191);
            $table->string('email', 191);
            $table->string('crc', 40)->nullable();
            $table->unsignedInteger('atualizado_por')->nullable();
            $table->timestamps();

            $table->foreign('business_id', 'nfe_contadores_business_fk')
                ->references('id')->on('business')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfe_contadores');
    }
};
