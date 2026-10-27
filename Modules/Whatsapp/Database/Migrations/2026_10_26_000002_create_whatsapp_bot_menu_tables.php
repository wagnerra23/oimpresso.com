<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-WA-324 — bot de atendimento com menu (etapa E4 do PLANO-ATENDIMENTO-AUTOMATICO, destravada
 * por [W] em 2026-10-26).
 *
 * `whatsapp_bot_menus`: um menu por canal (saudação, opções → fila, pergunta de nome).
 * `whatsapp_bot_menu_sessoes`: onde cada conversa está no menu (aguardando opção/nome, concluído).
 * Ambas com business_id (Tier 0, ADR 0093). Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_bot_menus')) {
            Schema::create('whatsapp_bot_menus', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id')->index();
                $table->unsignedBigInteger('channel_id');
                $table->boolean('ativo')->default(false);
                $table->text('saudacao');
                $table->json('opcoes')->comment('[{tecla, rotulo, fila (slug whatsapp_queues|null), resposta|null}]');
                $table->boolean('pergunta_nome')->default(false);
                $table->timestamps();

                $table->unique(['business_id', 'channel_id'], 'wbm_biz_canal_uq');
                $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('whatsapp_bot_menu_sessoes')) {
            Schema::create('whatsapp_bot_menu_sessoes', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id')->index();
                $table->unsignedBigInteger('conversation_id');
                $table->string('estado', 20)->comment('aguardando_opcao|aguardando_nome|concluido');
                $table->unsignedTinyInteger('tentativas')->default(0);
                $table->string('opcao', 10)->nullable();
                $table->timestamps();

                $table->unique(['business_id', 'conversation_id'], 'wbms_biz_conv_uq');
                $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_bot_menu_sessoes');
        Schema::dropIfExists('whatsapp_bot_menus');
    }
};
