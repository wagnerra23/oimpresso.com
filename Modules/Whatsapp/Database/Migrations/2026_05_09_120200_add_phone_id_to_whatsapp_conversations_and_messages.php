<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona FK `whatsapp_business_phone_id` em conversations + messages.
 *
 * ADR 0117 §Schema mãe — toda conversa/mensagem aponta pra 1 número
 * específico do business (não só pro business).
 *
 * Coluna nasce nullable nesta migration; data migration seguinte
 * (2026_05_09_120300_seed_whatsapp_business_phones_from_configs) preenche
 * com o phone_id correspondente. Após preencher, conversion pra NOT NULL
 * NÃO é feita aqui (legacy rollback fase 1 — ver runbook §Rollback) —
 * isso vira PR 5 depois de canary 30d.
 *
 * Multi-tenant Tier 0 (ADR 0093) — defensive: o Model adiciona scope
 * adicional `business_id = whatsapp_business_phones.business_id` em
 * service layer. FK aqui é só pra integridade referencial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('whatsapp_business_phone_id')->nullable()
                ->after('business_id')
                ->comment('FK whatsapp_business_phones — nullable até data migration rodar');
            $table->index('whatsapp_business_phone_id', 'wc_phone_idx');
        });

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('whatsapp_business_phone_id')->nullable()
                ->after('business_id')
                ->comment('FK whatsapp_business_phones — nullable até data migration rodar');
            $table->index('whatsapp_business_phone_id', 'wm_phone_idx');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->dropIndex('wc_phone_idx');
            $table->dropColumn('whatsapp_business_phone_id');
        });

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropIndex('wm_phone_idx');
            $table->dropColumn('whatsapp_business_phone_id');
        });
    }
};
