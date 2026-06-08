<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whatsapp messages — append-only.
 *
 * Espelha ARCHITECTURE.md §2.3. Cada mensagem (in/out) = 1 row imutável.
 * Updates permitidos só em status/failed_reason/updated_at (status delivery flow).
 *
 * Append-only enforcement em Lote 2c via Model observer + (futuro) trigger MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('conversation_id');

            $table->enum('direction', ['inbound', 'outbound']);
            $table->string('provider', 20)
                ->comment('zapi|meta_cloud|baileys|null — driver que enviou/recebeu');
            $table->string('provider_message_id', 128)->nullable()
                ->comment('wamid.XYZ (Meta) ou messageId (Z-API/Baileys)');

            $table->enum('type', ['text', 'template', 'image', 'document', 'audio', 'interactive', 'location', 'contacts'])
                ->default('text');
            $table->string('template_name', 64)->nullable();
            $table->text('body')->nullable();
            $table->json('payload')->nullable()
                ->comment('raw provider payload — auditoria');

            $table->enum('status', ['queued', 'sent', 'delivered', 'read', 'failed', 'received']);
            $table->string('failed_reason', 255)->nullable();

            $table->unsignedInteger('sender_user_id')->nullable()
                ->comment('só outbound humano');
            $table->enum('sender_kind', ['human', 'bot', 'system'])->nullable()
                ->comment('só outbound');

            $table->unsignedInteger('cost_centavos')->nullable()
                ->comment('custo Meta da conversa (1ª msg da janela); zero pra zapi/baileys');

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable()
                ->comment('só pra status updates do mesmo provider_message_id');

            $table->unique('provider_message_id', 'wm_provider_msg_uniq');
            $table->index(['business_id', 'conversation_id', 'created_at'], 'wm_biz_conv_created_idx');
            $table->index(['business_id', 'status', 'created_at'], 'wm_biz_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
