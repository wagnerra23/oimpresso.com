<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-WA-076 (ADR 0142 §5) — tabela `whatsapp_reminders`.
 *
 * Slash command `/lembrete <data> <body>` grava 1 row por lembrete agendado;
 * `ProcessRemindersJob` hourly busca rows com `due_at <= now()` AND
 * `status='pending'` AND `notified_at IS NULL` → publica Centrifugo no canal
 * `user:{atendente_user_id}` e marca `notified_at`.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093):
 *   - `business_id` indexado + (junto com `due_at`) composto pra job query
 *   - Model `WhatsappReminder` aplica global scope via `HasBusinessScope`
 *
 * Idempotente — `Schema::hasTable` guard para rodar duas vezes sem erro
 * (padrão útil em ambientes de dev que recriam via migrate sem fresh).
 *
 * @see memory/decisions/0142-notas-internas-sinal-treino-jana.md
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-076
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_reminders')) {
            return;
        }

        Schema::create('whatsapp_reminders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedInteger('contact_id')->nullable();
            $table->unsignedInteger('atendente_user_id')
                ->comment('quem deve ser notificado (default = quem criou)');
            $table->unsignedInteger('created_by_user_id')
                ->comment('audit — quem escreveu /lembrete na nota');
            $table->timestamp('due_at');
            $table->text('body');
            $table->string('status', 20)->default('pending')
                ->comment('pending|notified|done|cancelled');
            $table->timestamp('notified_at')->nullable()
                ->comment('preenchido pelo ProcessRemindersJob ao publicar Centrifugo');
            $table->timestamp('completed_at')->nullable()
                ->comment('atendente clica Concluir → done');
            $table->timestamps();

            // Job query usa (status, due_at) pra varrer pendentes vencidos.
            $table->index(['status', 'due_at'], 'wr_due_pending_idx');
            // UI "meus lembretes" usa (atendente_user_id, status).
            $table->index(['atendente_user_id', 'status'], 'wr_user_status_idx');
            // Multi-tenant scope index — query global scope filtra business_id.
            $table->index('business_id', 'wr_biz_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_reminders');
    }
};
