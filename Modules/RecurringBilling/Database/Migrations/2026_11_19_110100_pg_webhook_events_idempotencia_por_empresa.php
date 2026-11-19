<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoria RecurringBilling d5 (2026-11-19): a chave de idempotência de `pg_webhook_events`
 * era unique(provider, event_id), sem a empresa. Quem tinha o segredo do webhook da empresa A
 * podia "queimar" um id de evento da empresa B (o webhook de B passava a ser descartado como
 * duplicado). Passa a ser unique(business_id, provider, event_id).
 *
 * Idempotente pelos nomes dos índices. O down() volta à chave antiga e falha se, nesse meio
 * tempo, duas empresas tiverem gravado o mesmo (provider, event_id) — é o caso que esta
 * migration passa a permitir.
 */
return new class extends Migration
{
    private const ANTIGA = 'pg_webhook_idempotency';
    private const NOVA = 'pg_webhook_idem_biz';

    public function up(): void
    {
        if (! Schema::hasTable('pg_webhook_events')) {
            return;
        }

        if (! Schema::hasIndex('pg_webhook_events', self::NOVA)) {
            Schema::table('pg_webhook_events', fn (Blueprint $t) => $t->unique(['business_id', 'provider', 'event_id'], self::NOVA));
        }
        if (Schema::hasIndex('pg_webhook_events', self::ANTIGA)) {
            Schema::table('pg_webhook_events', fn (Blueprint $t) => $t->dropUnique(self::ANTIGA));
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('pg_webhook_events')) {
            return;
        }

        if (! Schema::hasIndex('pg_webhook_events', self::ANTIGA)) {
            Schema::table('pg_webhook_events', fn (Blueprint $t) => $t->unique(['provider', 'event_id'], self::ANTIGA));
        }
        if (Schema::hasIndex('pg_webhook_events', self::NOVA)) {
            Schema::table('pg_webhook_events', fn (Blueprint $t) => $t->dropUnique(self::NOVA));
        }
    }
};
