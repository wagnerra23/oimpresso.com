<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comprovantes da instalação de campo (US-COMVIS-007 · UC-PCV-25): foto antes, foto depois e a assinatura
 * do cliente. Uma linha por envio; o arquivo fica no módulo Arquivos (cofre privado, URL assinada), anexado
 * a esta linha. As colunas `foto_pre_url`/`foto_pos_url`/`assinatura_cliente_url` de `cv_instalacoes` eram
 * URL pública e nunca foram preenchidas: a assinatura é dado pessoal (LGPD) e não vai para pasta pública.
 *
 * A assinatura guarda o nome de quem assinou e quando o cliente aceitou guardá-la (`consentimento_em`,
 * anti-hook #7 do charter: sem consentimento não se armazena).
 *
 * Multi-tenant Tier 0 (ADR 0093): business_id indexado + FK.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('cv_instalacao_comprovantes')) {
            return;
        }
        Schema::create('cv_instalacao_comprovantes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('instalacao_id');
            $table->enum('tipo', ['foto_antes', 'foto_depois', 'assinatura']);
            $table->string('assinado_por', 120)->nullable()->comment('nome de quem assinou (só na assinatura)');
            $table->timestamp('consentimento_em')->nullable()->comment('LGPD: o cliente aceitou guardar a assinatura');
            $table->unsignedInteger('user_id')->nullable()->comment('quem colheu');
            $table->timestamps();

            $table->index('business_id', 'cv_inst_comp_biz_idx');
            $table->index(['instalacao_id', 'tipo'], 'cv_inst_comp_inst_tipo_idx');
            $table->foreign('business_id', 'cv_inst_comp_business_fk')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('instalacao_id', 'cv_inst_comp_inst_fk')->references('id')->on('cv_instalacoes')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_instalacao_comprovantes');
    }
};
