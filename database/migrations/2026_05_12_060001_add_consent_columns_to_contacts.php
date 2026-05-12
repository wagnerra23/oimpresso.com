<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-LGPD — colunas de consent (WhatsApp + email) em `contacts`.
 *
 * Conservador: colunas NULLABLE com default NULL.
 *  - NULL  = consent não definido (legacy pre-coluna) → ENVIA (back-compat)
 *  - TRUE  = cliente autorizou explicitamente         → ENVIA
 *  - FALSE = cliente recusou explicitamente           → BLOQUEIA + log
 *
 * Decisão NULL=permite preserva ROTA LIVRE (biz=4, 99% volume) — clientes
 * pre-coluna não param de receber notificação ao subir migration. Opt-in
 * gradual via UI admin de privacidade (US futura).
 *
 * Migration idempotente: hasTable + hasColumn guards permitem replay sem
 * quebrar prod (mesma postura US-COPI-092 procedure_drift).
 *
 * Refs: ADR 0093 multi-tenant Tier 0, ADR 0094 §5 SoC brutal,
 * `Modules/Whatsapp/Jobs/NotificarClienteCancelamentoJob.php` consume.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contacts')) {
            return;
        }

        Schema::table('contacts', function (Blueprint $t) {
            if (! Schema::hasColumn('contacts', 'whatsapp_consent')) {
                $t->boolean('whatsapp_consent')->nullable()->after('mobile');
            }
            if (! Schema::hasColumn('contacts', 'email_consent')) {
                $t->boolean('email_consent')->nullable()->after('email');
            }
            if (! Schema::hasColumn('contacts', 'consent_updated_at')) {
                $t->timestamp('consent_updated_at')->nullable()->after('email_consent');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('contacts')) {
            return;
        }

        Schema::table('contacts', function (Blueprint $t) {
            foreach (['whatsapp_consent', 'email_consent', 'consent_updated_at'] as $col) {
                if (Schema::hasColumn('contacts', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
