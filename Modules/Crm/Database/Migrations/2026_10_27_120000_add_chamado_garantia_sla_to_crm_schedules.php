<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-CRM-094 — chamado de garantia com SLA.
 *
 * O chamado é um acompanhamento do CRM (`crm_schedules`), não uma tabela de tickets nova:
 * responsáveis já moram em `crm_schedule_users`, a venda em `crm_followup_invoices` e o tipo
 * na categoria de acompanhamento. Aqui entram só o que faltava: marca de garantia, fila,
 * prazo do SLA, momento do alerta e a OS do Repair. A política do prazo por tipo fica em
 * `crm_sla_politicas`, uma linha por categoria (categoria nula = padrão da empresa).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('crm_schedules', 'garantia')) {
            Schema::table('crm_schedules', function (Blueprint $table) {
                $table->boolean('garantia')->default(false);
                $table->string('fila', 60)->nullable();
                $table->dateTime('sla_vence_em')->nullable();
                $table->dateTime('sla_alertado_em')->nullable();
                $table->unsignedInteger('repair_job_sheet_id')->nullable();
                $table->index(['business_id', 'garantia', 'sla_vence_em'], 'crm_sched_biz_garantia_sla_idx');
            });
        }

        if (! Schema::hasTable('crm_sla_politicas')) {
            Schema::create('crm_sla_politicas', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id');
                $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
                $table->integer('followup_category_id')->nullable();
                $table->unsignedSmallInteger('prazo_horas');
                $table->string('fila', 60)->nullable();
                $table->unsignedInteger('responsavel_user_id')->nullable();
                $table->boolean('ativo')->default(true);
                $table->timestamps();
                $table->unique(['business_id', 'followup_category_id'], 'crm_sla_politicas_biz_cat_uq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_sla_politicas');

        if (Schema::hasColumn('crm_schedules', 'garantia')) {
            Schema::table('crm_schedules', function (Blueprint $table) {
                $table->dropIndex('crm_sched_biz_garantia_sla_idx');
                $table->dropColumn(['garantia', 'fila', 'sla_vence_em', 'sla_alertado_em', 'repair_job_sheet_id']);
            });
        }
    }
};
