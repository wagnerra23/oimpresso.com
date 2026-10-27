<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-BKP-001 — pedidos de exportação dos dados de uma empresa.
 *
 * Um pedido por clique do dono: `pendente` → `pronto` (ou `falhou`). O zip fica no disco
 * `backup.exportacao_empresa.disco` e só é baixado por link assinado até `expira_em`.
 * `baixado_em`/`downloads` contam o download; o registro de atividades guarda quem baixou.
 *
 * Tier 0 (ADR 0093): `business_id` indexado + FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('exportacoes_empresa')) {
            return;
        }
        Schema::create('exportacoes_empresa', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('solicitado_por');
            $table->enum('status', ['pendente', 'pronto', 'falhou'])->default('pendente');
            $table->string('arquivo', 191)->nullable();
            $table->unsignedBigInteger('bytes')->nullable();
            $table->json('manifesto')->nullable();
            $table->string('erro', 191)->nullable();
            $table->timestamp('expira_em')->nullable();
            $table->timestamp('baixado_em')->nullable();
            $table->unsignedInteger('downloads')->default(0);
            $table->timestamps();

            $table->index(['business_id', 'created_at'], 'exp_emp_biz_criado_idx');
            $table->foreign('business_id', 'exp_emp_biz_fk')->references('id')->on('business')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exportacoes_empresa');
    }
};
