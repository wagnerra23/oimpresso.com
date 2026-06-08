<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-WA-022 — UX simplificada Baileys.
 *
 * Movimentações:
 * - REMOVE `baileys_daemon_url` + `baileys_api_key` — passam pra
 *   `config/whatsapp.php` (env vars `.env` Hostinger). Tenant não vê infra.
 * - ADD `baileys_phone_e164` VARCHAR(20) — único campo que tenant cadastra.
 * - ADD `baileys_verified_name` VARCHAR(100) NULL — Business Profile sync.
 * - ADD `baileys_profile_pic_url` VARCHAR(255) NULL — sync após pareamento.
 * - UNIQUE (business_id, baileys_phone_e164) — anti-duplicate.
 *
 * `baileys_instance_id` mantido — passa a ser AUTO-GERADO pelo backend
 * com prefixo "biz{business_id}-" no SettingsController/BaileysConnectJob.
 *
 * Charter mãe: resources/js/Pages/Whatsapp/Settings.charter.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_business_configs', function (Blueprint $table) {
            // Adicionar novas colunas
            $table->string('baileys_phone_e164', 20)->nullable()
                ->after('baileys_instance_id')
                ->comment('telefone E.164 (+5511987654321) cadastrado pelo tenant');
            $table->string('baileys_verified_name', 100)->nullable()
                ->after('baileys_phone_e164')
                ->comment('Business Profile name sincronizado após pareamento');
            $table->string('baileys_profile_pic_url', 255)->nullable()
                ->after('baileys_verified_name')
                ->comment('URL da foto de perfil sincronizada após pareamento');

            // UNIQUE composto — anti-duplicate (mesmo número 1x por business)
            $table->unique(['business_id', 'baileys_phone_e164'], 'wbc_biz_phone_unq');
        });

        // Drop colunas em separado (algumas DBs exigem outro statement)
        Schema::table('whatsapp_business_configs', function (Blueprint $table) {
            $table->dropColumn(['baileys_daemon_url', 'baileys_api_key']);
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_business_configs', function (Blueprint $table) {
            // Restaura colunas (sem dados — é rollback dev)
            $table->string('baileys_daemon_url', 255)->nullable();
            $table->text('baileys_api_key')->nullable();
        });

        Schema::table('whatsapp_business_configs', function (Blueprint $table) {
            $table->dropUnique('wbc_biz_phone_unq');
            $table->dropColumn([
                'baileys_phone_e164',
                'baileys_verified_name',
                'baileys_profile_pic_url',
            ]);
        });
    }
};
