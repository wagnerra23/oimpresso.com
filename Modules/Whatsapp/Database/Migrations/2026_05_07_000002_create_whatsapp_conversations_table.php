<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whatsapp conversations — 1 conversa = par (business + customer_phone).
 *
 * Espelha ARCHITECTURE.md §2.2. Multi-tenant Tier 0 (ADR 0093).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('contact_id')->nullable()
                ->comment('contacts.id, NULL se provisional');
            $table->string('customer_phone', 20)
                ->comment('normalizado +5511987654321');

            $table->enum('status', ['open', 'awaiting_human', 'resolved', 'archived'])
                ->default('open');
            $table->unsignedInteger('assigned_user_id')->nullable()
                ->comment('users.id atendente');
            $table->boolean('bot_handling')->default(false);

            $table->timestamp('last_inbound_at')->nullable()
                ->comment('última msg cliente — usado pra janela 24h Meta');
            $table->timestamp('last_outbound_at')->nullable();
            $table->timestamp('last_message_at')->nullable()
                ->comment('maior(in,out) — sort lista');

            $table->unsignedInteger('unread_count')->default(0);

            $table->timestamps();

            $table->unique(['business_id', 'customer_phone'], 'wc_biz_phone_uniq');
            $table->index(['business_id', 'last_message_at'], 'wc_biz_lastmsg_idx');
            $table->index(['business_id', 'status'], 'wc_biz_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conversations');
    }
};
