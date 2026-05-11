<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HOTFIX US-WA-063: adicionar `updated_at` ao pivot `whatsapp_conversation_tags`.
 *
 * Wagner 2026-05-11: "https://oimpresso.com/atendimento/inbox por favor arrume"
 *
 * Bug observado em prod (Hostinger laravel.log 2026-05-11 18:14:17):
 *   SQLSTATE[42S22]: Column not found: 1054 Unknown column
 *   'whatsapp_conversation_tags.updated_at' in 'SELECT'
 *
 * A migration original (2026_05_11_120000) criou só `created_at` mas a
 * relation `Conversation::tags()->belongsToMany()->withTimestamps()` exige
 * AMBOS columns no pivot. Pest test passou porque o schema do beforeEach
 * tinha `updated_at` (espelhou comportamento esperado — não a migration
 * real), mascarando o bug até produção.
 *
 * Lição: tests precisam espelhar a migration EXATA, não a intenção. Adicionado
 * ao charter como anti-pattern em US-WA-091b (test schema drift).
 *
 * Idempotente: só adiciona se não existir.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_conversation_tags')) {
            return;
        }
        if (Schema::hasColumn('whatsapp_conversation_tags', 'updated_at')) {
            return;
        }

        Schema::table('whatsapp_conversation_tags', function (Blueprint $table) {
            $table->timestamp('updated_at')->nullable()->after('created_at');
        });

        // Backfill: updated_at = created_at pra rows existentes (raro, mas
        // ROTA LIVRE ou biz=1 pode ter aplicado tags antes do hotfix)
        \DB::table('whatsapp_conversation_tags')
            ->whereNull('updated_at')
            ->update(['updated_at' => \DB::raw('created_at')]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('whatsapp_conversation_tags', 'updated_at')) {
            return;
        }
        Schema::table('whatsapp_conversation_tags', function (Blueprint $table) {
            $table->dropColumn('updated_at');
        });
    }
};
