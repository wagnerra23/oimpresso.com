<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-WA-075 (ADR 0142 §3a) — Tabela `whatsapp_jana_correcoes`.
 *
 * Sinal de treino: atendente vê resposta errada do bot, escreve em nota
 * interna `/corrigir Deveria dizer X` e a row aqui guarda o par
 * (mensagem-errada → correção-humana) pra fine-tune/few-shot futuro.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) — `business_id` indexado +
 * global scope no Model. Sem FK pra `business` (legacy UltimatePOS é
 * `unsignedInteger`, ver reference_ultimatepos_integracao).
 *
 * FK CASCADE em `message_id_errada` → se a msg do bot for deletada, a
 * correção também cai (sem referência fica órfã sem valor de treino).
 *
 * Migration idempotente (`Schema::hasTable` guard) — pode rodar 2x sem
 * quebrar.
 *
 * @see memory/decisions/0142-notas-internas-sinal-treino-jana.md §3a
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-075
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_jana_correcoes')) {
            return;
        }

        Schema::create('whatsapp_jana_correcoes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('message_id_errada')
                ->comment('FK pra messages(id) — msg do bot que foi corrigida');
            $table->text('correcao_texto')
                ->comment('"Deveria ter dito X" — fornecido pelo atendente humano');
            $table->unsignedInteger('contact_id')->nullable()
                ->comment('FK opcional contacts UltimatePOS — denormalizado da conv');
            $table->unsignedInteger('atendente_user_id')
                ->comment('User que corrigiu (sender_user_id da nota)');
            $table->string('training_status', 20)->default('pending_review')
                ->comment('pending_review | exported_for_fine_tune | rejected | applied');
            $table->json('metadata')->nullable()
                ->comment('tokens, modelo usado, source_message_id da nota, etc');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            // Indexes — convenção (biz, status) pra filtrar dashboard rápido,
            // (msg) pra encontrar correções de uma mensagem específica.
            $table->index(['business_id', 'training_status'], 'wjc_biz_status_idx');
            $table->index('message_id_errada', 'wjc_msg_idx');

            // FK CASCADE — se msg do bot for deletada (raro, append-only schema),
            // correção cai junto pq sem referência não tem valor de treino.
            $table->foreign('message_id_errada')
                ->references('id')->on('messages')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_jana_correcoes');
    }
};
