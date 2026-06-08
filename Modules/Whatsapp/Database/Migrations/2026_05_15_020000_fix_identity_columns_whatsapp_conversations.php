<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HOTFIX migration 2026_05_15_010000 — campo `after()` quebrava em
 * `whatsapp_conversations`.
 *
 * Migration original assume `customer_external_id` em ambas tabelas, mas
 * `whatsapp_conversations` usa `customer_phone` (schema pré-omnichannel
 * diferente). Resultado: migration original passou em `conversations`
 * (legacy prod biz=1) mas falhou ao aplicar em `whatsapp_conversations`
 * (ADR 0135 nova schema, zerada hoje).
 *
 * Esta hotfix:
 *
 *  1. Adiciona `lid`/`phone_e164`/`bsuid` em `whatsapp_conversations`
 *     SEM `after()` (vai pro final da tabela — não muda semântica).
 *  2. Detecta automaticamente schema correto via `Schema::hasColumn`.
 *  3. Marca a migration anterior 010000 como ran (já passou na tabela
 *     legacy — o que importa pra prod).
 *
 * Idempotente — re-run safe via guards `hasColumn`.
 *
 * @see Modules/Whatsapp/Database/Migrations/2026_05_15_010000_add_identity_columns_to_conversations.php
 * @see memory/sessions/2026-05-15-estudo-whatsapp-protocol-vs-oimpresso.md
 */
return new class extends Migration
{
    public function up(): void
    {
        // Marca migration anterior como ran se já aplicou parcial em
        // `conversations` (prod biz=1) — evita re-execução que travaria
        // de novo no `after('customer_external_id')` de whatsapp_conversations.
        if (Schema::hasColumn('conversations', 'lid') && Schema::hasTable('migrations')) {
            $previousMigration = '2026_05_15_010000_add_identity_columns_to_conversations';
            $existing = \DB::table('migrations')->where('migration', $previousMigration)->exists();
            if (! $existing) {
                $batch = (int) \DB::table('migrations')->max('batch') ?: 0;
                \DB::table('migrations')->insert([
                    'migration' => $previousMigration,
                    'batch' => $batch + 1,
                ]);
            }
        }

        // Aplica 3 colunas em whatsapp_conversations (sem `after()`)
        if (Schema::hasTable('whatsapp_conversations')) {
            Schema::table('whatsapp_conversations', function (Blueprint $t) {
                if (! Schema::hasColumn('whatsapp_conversations', 'lid')) {
                    $t->string('lid', 100)->nullable();
                    $t->index(
                        ['business_id', 'lid'],
                        substr('whatsapp_conversations_biz_lid_idx', 0, 60),
                    );
                }
            });

            Schema::table('whatsapp_conversations', function (Blueprint $t) {
                if (! Schema::hasColumn('whatsapp_conversations', 'phone_e164')) {
                    $t->string('phone_e164', 30)->nullable();
                    $t->index(
                        ['business_id', 'phone_e164'],
                        substr('whatsapp_conversations_biz_phone_idx', 0, 60),
                    );
                }
            });

            Schema::table('whatsapp_conversations', function (Blueprint $t) {
                if (! Schema::hasColumn('whatsapp_conversations', 'bsuid')) {
                    $t->string('bsuid', 100)->nullable();
                    $t->index(
                        ['business_id', 'bsuid'],
                        substr('whatsapp_conversations_biz_bsuid_idx', 0, 60),
                    );
                }
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('whatsapp_conversations')) {
            return;
        }

        Schema::table('whatsapp_conversations', function (Blueprint $t) {
            if (Schema::hasColumn('whatsapp_conversations', 'bsuid')) {
                $t->dropIndex(substr('whatsapp_conversations_biz_bsuid_idx', 0, 60));
                $t->dropColumn('bsuid');
            }
        });

        Schema::table('whatsapp_conversations', function (Blueprint $t) {
            if (Schema::hasColumn('whatsapp_conversations', 'phone_e164')) {
                $t->dropIndex(substr('whatsapp_conversations_biz_phone_idx', 0, 60));
                $t->dropColumn('phone_e164');
            }
        });

        Schema::table('whatsapp_conversations', function (Blueprint $t) {
            if (Schema::hasColumn('whatsapp_conversations', 'lid')) {
                $t->dropIndex(substr('whatsapp_conversations_biz_lid_idx', 0, 60));
                $t->dropColumn('lid');
            }
        });
    }
};
