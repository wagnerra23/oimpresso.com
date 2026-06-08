<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whatsapp templates — espelho local dos HSM Meta Cloud + templates locais Z-API/Baileys.
 *
 * Espelha ARCHITECTURE.md §2.4.
 *
 * Comportamento por driver:
 * - Meta Cloud: templates sincronizados via MetaCloudDriver::fetchTemplates();
 *   status reflete aprovação Meta; outbound fora janela 24h EXIGE template aprovado.
 * - Z-API / Baileys: templates são LOCAL (status=LOCAL); driver expande
 *   placeholders e manda como freeform; sem janela 24h restritiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->string('provider', 20)->default('zapi')
                ->comment('zapi|meta_cloud|baileys (locais) ou meta_cloud (HSM)');

            $table->string('meta_template_id', 64)->nullable()
                ->comment('só pra provider=meta_cloud');
            $table->string('name', 64);
            $table->string('language', 10)->default('pt_BR');
            $table->enum('category', ['UTILITY', 'MARKETING', 'AUTHENTICATION']);
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED', 'PAUSED', 'DISABLED', 'LOCAL'])
                ->comment('LOCAL = template Z-API/Baileys sempre disponível');
            $table->json('components')
                ->comment('estrutura header/body/footer/buttons');
            $table->string('rejection_reason', 255)->nullable();
            $table->timestamp('last_synced_at')->nullable();

            $table->timestamps();

            $table->unique(['business_id', 'provider', 'name', 'language'], 'wt_biz_prov_name_lang_uniq');
            $table->index(['business_id', 'status'], 'wt_biz_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
