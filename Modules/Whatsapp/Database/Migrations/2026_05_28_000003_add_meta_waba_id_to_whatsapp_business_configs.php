<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-WA-310 Fase 2 (ADR 0202) — Embedded Signup v4 precisa persistir o
 * `whatsapp_business_account_id` (WABA) que o OAuth do Facebook devolve.
 *
 * Por que coluna nova:
 * - `meta_phone_number_id` aponta pro número (PHONE_NUMBER_ID),
 *   mas o WABA é entidade-irmã (1 WABA pode ter N phones)
 * - `fetchTemplates()` hoje busca WABA via GET phone_number_id em runtime;
 *   manter cache local evita hit Graph extra a cada listagem de templates
 * - Permite Embedded Signup auto-subscribe webhook em `/{waba_id}/subscribed_apps`
 *   sem precisar resolver phone→WABA toda vez
 *
 * Idempotente: checa schema (`hasColumn`) antes de adicionar — pode rodar 2× sem dropar.
 *
 * @see Modules\Whatsapp\Services\Drivers\MetaCloudDriver::provisionViaEmbeddedSignup
 * @see memory/decisions/0202-whatsapp-profissionalizacao-baileys-out.md
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_business_configs')) {
            // Tabela legacy pode não existir em ambientes pós-ADR 0117 que migraram tudo
            // pra `whatsapp_business_phones`. Migration silenciosa nesse caso.
            return;
        }

        if (Schema::hasColumn('whatsapp_business_configs', 'meta_waba_id')) {
            return;
        }

        Schema::table('whatsapp_business_configs', function (Blueprint $table) {
            $table->string('meta_waba_id', 64)
                ->nullable()
                ->after('meta_phone_number_id')
                ->comment('WhatsApp Business Account ID (Embedded Signup v4)');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('whatsapp_business_configs')) {
            return;
        }

        if (! Schema::hasColumn('whatsapp_business_configs', 'meta_waba_id')) {
            return;
        }

        Schema::table('whatsapp_business_configs', function (Blueprint $table) {
            $table->dropColumn('meta_waba_id');
        });
    }
};
