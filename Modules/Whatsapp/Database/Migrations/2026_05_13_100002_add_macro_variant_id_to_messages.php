<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-WA-049 — Adiciona `macro_variant_id` em `messages` pra rastrear qual
 * variante de macro foi usada no envio (gap P2 #18 A/B testing).
 *
 * Coluna nullable — só populada quando msg foi disparada via macro com
 * variantes ativas. Permite reverse lookup: "quantas msgs da variante X
 * foram enviadas no ultimo mes?" + tracking de resposta inbound em 24h.
 *
 * ON DELETE SET NULL — variante deletada nao perde histórico de msgs
 * (preserva audit append-only do schema messages, ADR 0135).
 *
 * Migration idempotente (Schema::hasColumn guard) — dual-mode SQLite/MySQL.
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-049
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('messages')) {
            return;
        }

        if (Schema::hasColumn('messages', 'macro_variant_id')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table) {
            $table->unsignedBigInteger('macro_variant_id')
                ->nullable()
                ->after('payload')
                ->comment('US-WA-049: variante de macro usada no envio (NULL = nao via macro variant)');
            $table->index('macro_variant_id', 'msgs_macro_variant_idx');
        });

        // FK só em MySQL — SQLite tests usam dual-mode sem FK strict.
        if (config('database.default') !== 'sqlite') {
            Schema::table('messages', function (Blueprint $table) {
                $table->foreign('macro_variant_id')
                    ->references('id')->on('macro_variants')
                    ->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('messages') || ! Schema::hasColumn('messages', 'macro_variant_id')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table) {
            if (config('database.default') !== 'sqlite') {
                try {
                    $table->dropForeign(['macro_variant_id']);
                } catch (\Throwable $e) {
                    // FK pode nao existir se up nao criou — ignora.
                }
            }
            $table->dropIndex('msgs_macro_variant_idx');
            $table->dropColumn('macro_variant_id');
        });
    }
};
