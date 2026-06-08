<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0202 (2026-05-27) — BaileysDriver descontinuado integral.
 *
 * DROP colunas baileys_* em whatsapp_business_configs + whatsapp_business_phones.
 *
 * Pre-flight Fase 0 confirmado:
 *  - 0 rows com driver=baileys em whatsapp_business_configs (produção MySQL Hostinger)
 *  - 0 mensagens com provider=baileys em whatsapp_messages
 *  - tabela whatsapp_baileys_auth_state 0 rows
 *
 * Idempotente: Schema::hasColumn antes de qualquer dropColumn.
 *
 * @see memory/decisions/0202-whatsapp-profissionalizacao-baileys-out.md
 * @see memory/sessions/2026-05-27-dossier-whatsapp-profissionalizacao.md
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- whatsapp_business_configs ----
        if (Schema::hasTable('whatsapp_business_configs')) {
            // UNIQUE composto (criado em 2026_05_09_000001_simplify_baileys_columns) precisa
            // ser dropado antes da coluna. Tenta dropar by name — silencia se não existir.
            try {
                Schema::table('whatsapp_business_configs', function (Blueprint $table) {
                    $table->dropUnique('wbc_biz_phone_unq');
                });
            } catch (\Throwable $e) {
                // Index já não existe (migration fresh DB ou prod sem o índice) — segue.
            }

            $columnsToDrop = [];
            foreach (['baileys_instance_id', 'baileys_phone_e164', 'baileys_verified_name', 'baileys_profile_pic_url'] as $col) {
                if (Schema::hasColumn('whatsapp_business_configs', $col)) {
                    $columnsToDrop[] = $col;
                }
            }
            if (!empty($columnsToDrop)) {
                Schema::table('whatsapp_business_configs', function (Blueprint $table) use ($columnsToDrop) {
                    $table->dropColumn($columnsToDrop);
                });
            }
        }

        // ---- whatsapp_business_phones ----
        if (Schema::hasTable('whatsapp_business_phones')) {
            // UNIQUE wbp_biz_phone_unq (criado em 2026_05_09_120000) — drop antes da coluna.
            try {
                Schema::table('whatsapp_business_phones', function (Blueprint $table) {
                    $table->dropUnique('wbp_biz_phone_unq');
                });
            } catch (\Throwable $e) {
                // Index não existe — segue.
            }

            $columnsToDrop = [];
            foreach (['baileys_instance_id', 'baileys_phone_e164', 'baileys_verified_name', 'baileys_profile_pic_url'] as $col) {
                if (Schema::hasColumn('whatsapp_business_phones', $col)) {
                    $columnsToDrop[] = $col;
                }
            }
            if (!empty($columnsToDrop)) {
                Schema::table('whatsapp_business_phones', function (Blueprint $table) use ($columnsToDrop) {
                    $table->dropColumn($columnsToDrop);
                });
            }
        }
    }

    public function down(): void
    {
        // Rollback dev: recria colunas vazias (dado já perdido em prod).
        // Schema reversível mas conteúdo não retorna — explicitar via runbook
        // se cenário real precisar voltar.

        if (Schema::hasTable('whatsapp_business_configs')) {
            Schema::table('whatsapp_business_configs', function (Blueprint $table) {
                if (!Schema::hasColumn('whatsapp_business_configs', 'baileys_instance_id')) {
                    $table->string('baileys_instance_id', 64)->nullable();
                }
                if (!Schema::hasColumn('whatsapp_business_configs', 'baileys_phone_e164')) {
                    $table->string('baileys_phone_e164', 20)->nullable();
                }
                if (!Schema::hasColumn('whatsapp_business_configs', 'baileys_verified_name')) {
                    $table->string('baileys_verified_name', 100)->nullable();
                }
                if (!Schema::hasColumn('whatsapp_business_configs', 'baileys_profile_pic_url')) {
                    $table->string('baileys_profile_pic_url', 255)->nullable();
                }
            });

            // Recria UNIQUE composto SOMENTE se coluna baileys_phone_e164 está presente
            if (Schema::hasColumn('whatsapp_business_configs', 'baileys_phone_e164')) {
                try {
                    Schema::table('whatsapp_business_configs', function (Blueprint $table) {
                        $table->unique(['business_id', 'baileys_phone_e164'], 'wbc_biz_phone_unq');
                    });
                } catch (\Throwable $e) {
                    // Já existe — ok.
                }
            }
        }

        if (Schema::hasTable('whatsapp_business_phones')) {
            Schema::table('whatsapp_business_phones', function (Blueprint $table) {
                if (!Schema::hasColumn('whatsapp_business_phones', 'baileys_instance_id')) {
                    $table->string('baileys_instance_id', 64)->nullable();
                }
                if (!Schema::hasColumn('whatsapp_business_phones', 'baileys_phone_e164')) {
                    $table->string('baileys_phone_e164', 20)->nullable();
                }
                if (!Schema::hasColumn('whatsapp_business_phones', 'baileys_verified_name')) {
                    $table->string('baileys_verified_name', 100)->nullable();
                }
                if (!Schema::hasColumn('whatsapp_business_phones', 'baileys_profile_pic_url')) {
                    $table->string('baileys_profile_pic_url', 255)->nullable();
                }
            });

            if (Schema::hasColumn('whatsapp_business_phones', 'baileys_phone_e164')) {
                try {
                    Schema::table('whatsapp_business_phones', function (Blueprint $table) {
                        $table->unique(['business_id', 'baileys_phone_e164'], 'wbp_biz_phone_unq');
                    });
                } catch (\Throwable $e) {
                    // Já existe — ok.
                }
            }
        }
    }
};
