<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notas internas (US-WA-071, ADR 0142) — coluna `is_internal_note` em
 * `messages` (omnichannel novo) E `whatsapp_messages` (legacy). Defense-in-depth
 * durante coexistência dos 2 schemas (ADR 0135 §Coexistência).
 *
 * Quando true, dispatch driver é PROIBIDO Tier 0 — gate aplicado no
 * `InboxController::send()` antes de chamar HTTP daemon.
 *
 * Migration idempotente (Schema::hasColumn guards) — pode rodar 2x sem
 * quebrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('messages') && ! Schema::hasColumn('messages', 'is_internal_note')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->boolean('is_internal_note')
                    ->default(false)
                    ->after('sender_kind')
                    ->comment('US-WA-071: true = nota interna, NUNCA dispatch driver (Tier 0)');
                $table->index(
                    ['business_id', 'conversation_id', 'is_internal_note'],
                    'msgs_biz_conv_internal_idx'
                );
            });
        }

        if (Schema::hasTable('whatsapp_messages') && ! Schema::hasColumn('whatsapp_messages', 'is_internal_note')) {
            Schema::table('whatsapp_messages', function (Blueprint $table) {
                $table->boolean('is_internal_note')
                    ->default(false)
                    ->after('sender_kind')
                    ->comment('US-WA-071: defense-in-depth legacy schema');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('messages') && Schema::hasColumn('messages', 'is_internal_note')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropIndex('msgs_biz_conv_internal_idx');
                $table->dropColumn('is_internal_note');
            });
        }

        if (Schema::hasTable('whatsapp_messages') && Schema::hasColumn('whatsapp_messages', 'is_internal_note')) {
            Schema::table('whatsapp_messages', function (Blueprint $table) {
                $table->dropColumn('is_internal_note');
            });
        }
    }
};
