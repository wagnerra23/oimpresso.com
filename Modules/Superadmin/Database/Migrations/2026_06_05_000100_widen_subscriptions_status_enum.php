<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Widen subscriptions.status enum (additive) — destrava SubscriptionLifecycleService.
 *
 * Original (2018_06_28_182803_create_subscriptions_table): enum('approved','waiting','declined').
 * SubscriptionLifecycleService (approve/expire/cancel) escreve 'expired' e 'cancelled',
 * que NÃO existiam no enum → em MySQL strict mode lançaria ao gravar (bug latente, serviço
 * ainda não-wired). BusinessAuditService::subscriptionAgingSummary() e BusinessController já
 * esperam os buckets 'expired'/'cancelled' → o enum é que estava incompleto.
 *
 * ADITIVA: só acrescenta valores, não remove nem renomeia. Backward-compatible — código que
 * checa 'approved'/'waiting'/'declined' segue intacto. Tabela Tier 0 cross-tenant billing
 * (ADR 0093 §exceções Superadmin Wagner-only) — alteração de schema sem dado de tenant.
 *
 * MySQL-only: enum widening usa ALTER ... MODIFY (sintaxe MySQL). SQLite (test :memory:) não
 * exercita o CHECK aqui — os testes que persistem 'expired'/'cancelled' são MySQL-only
 * (markTestSkipped em sqlite). No-op em outros drivers evita erro no pipeline.
 *
 * Refs: ADR 0208 (larastan baseline ratchet — fecha os 4 erros do serviço na origem),
 *       ADR 0093 (multi-tenant Tier 0 §exceções).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE `subscriptions` MODIFY `status` "
            ."ENUM('approved','waiting','declined','expired','cancelled') NOT NULL DEFAULT 'waiting'"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Reverte pro enum de 3 valores. Seguro apenas se nenhuma linha estiver em
        // 'expired'/'cancelled' (do contrário o MODIFY trunca pra '' em non-strict
        // ou lança em strict). Serviço não-wired → sem linhas nesses estados ainda.
        DB::statement(
            "ALTER TABLE `subscriptions` MODIFY `status` "
            ."ENUM('approved','waiting','declined') NOT NULL DEFAULT 'waiting'"
        );
    }
};
