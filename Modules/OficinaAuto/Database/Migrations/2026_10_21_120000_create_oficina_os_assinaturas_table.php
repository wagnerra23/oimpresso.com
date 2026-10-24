<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assinatura do cliente na entrega da OS (UC-FLX-22 · US-OFICINA-048): uma por OS, colhida no quadro
 * do checklist da entrega. O PNG fica no módulo Arquivos, anexado a ESTA linha (não à OS): anexo da OS
 * é foto do laudo e entra na galeria, no laudo A4 e na regra "foto do serviço" — a assinatura não pode.
 *
 * Multi-tenant Tier 0 (ADR 0093): business_id indexado + FK. Sem valor, estoque ou cobrança.
 * Aditiva e idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('oficina_os_assinaturas')) {
            return;
        }

        Schema::create('oficina_os_assinaturas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('service_order_id');
            $table->string('recebido_por', 120)->nullable()->comment('nome de quem assinou, digitado no balcão');
            $table->unsignedInteger('user_id')->nullable()->comment('quem colheu');
            $table->timestamps();

            $table->unique('service_order_id', 'oficina_os_assin_os_uq');
            $table->index('business_id', 'oficina_os_assin_biz_idx');
            $table->foreign('business_id', 'oficina_os_assin_business_fk')
                ->references('id')->on('business')->cascadeOnDelete();
            $table->foreign('service_order_id', 'oficina_os_assin_os_fk')
                ->references('id')->on('service_orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oficina_os_assinaturas');
    }
};
