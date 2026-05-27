<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modules/Whatsapp — clients_feedbacks (canon Voice of Customer in-app)
 *
 * Wagner 2026-05-27: integrar captura de feedback diretamente no inbox WhatsApp.
 * Refs: ADR UI-0016 (design contextualizado por persona), ADR 0093 (multi-tenant
 * Tier 0), ADR 0105 (cliente-como-sinal), feedback-management RUNBOOK.
 *
 * Cada feedback nasce quando Wagner clica "Capturar" em uma mensagem do inbox.
 * Captura é leve (~15s), persona auto-detectada via phone match, severity NN/g 0-4.
 * Severity ≥ 3 dispara MCP task automaticamente (Observer separado).
 *
 * Sync para git canon (memory/clientes/<x>/feedback/*.md) via job semanal
 * ExportFeedbackToGitJob (digest agregado — não polui git com 1 commit por feedback).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('clients_feedbacks')) {
            return;
        }

        Schema::create('clients_feedbacks', function (Blueprint $table) {
            $table->id();

            // ── Tier 0 multi-tenant (ADR 0093) ──
            $table->unsignedInteger('business_id')->index();

            // ── Contato + mensagem origem ──
            $table->unsignedBigInteger('contact_id')->nullable()->index();
            $table->unsignedBigInteger('source_message_id')->nullable()->index();
            $table->unsignedBigInteger('conversation_id')->nullable()->index();

            // ── Persona (link para memory/clientes/<x>/personas/<slug>.yml) ──
            $table->string('persona_slug', 80)->nullable()->index();
            $table->string('cliente_slug', 80)->nullable();

            // ── O que disse (literal preservado — PII Tier 0) ──
            $table->string('canal', 32)->default('whatsapp');
            $table->text('literal');
            $table->text('contexto')->nullable();

            // ── Onde no produto ──
            $table->string('modulo_afetado', 80)->nullable();
            $table->string('tela_afetada', 160)->nullable();
            $table->string('acao_afetada', 80)->nullable();

            // ── Job-to-be-done (Mom Test reverso) ──
            $table->string('job', 255)->nullable();
            $table->string('motivacao_tipo', 24)->nullable(); // funcional | emocional | social

            // ── Workaround atual ──
            $table->string('workaround_o_que_faz', 255)->nullable();
            $table->string('workaround_custo', 255)->nullable();

            // ── Severity NN/g (1995) 0-4 ──
            $table->unsignedTinyInteger('severity_nng')->default(2);

            // ── Frequência ──
            $table->boolean('primeira_vez')->default(true);
            $table->unsignedSmallInteger('recorrente_count')->default(1);
            $table->boolean('pattern_emergente')->default(false);

            // ── Status workflow ──
            // novo → triaged → backlog → in_progress → resolved → closed
            $table->string('status', 20)->default('novo')->index();
            $table->text('responder_cliente')->nullable();

            // ── Link MCP task (severity ≥ 3 cria automaticamente) ──
            $table->string('mcp_task_id', 80)->nullable();

            // ── Resolução ──
            $table->timestamp('data_resolvido')->nullable();
            $table->string('pr_link', 255)->nullable();
            $table->boolean('cliente_confirmou')->nullable();
            $table->boolean('re_reclamacao')->default(false);

            // ── Audit ──
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // ── Indexes pra dashboard ──
            $table->index(['business_id', 'status'], 'idx_biz_status');
            $table->index(['business_id', 'persona_slug'], 'idx_biz_persona');
            $table->index(['business_id', 'severity_nng'], 'idx_biz_severity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients_feedbacks');
    }
};
