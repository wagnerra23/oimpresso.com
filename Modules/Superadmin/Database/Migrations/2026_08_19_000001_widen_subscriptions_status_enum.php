<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Amplia o enum `subscriptions.status` com `expired` e `cancelled`.
 *
 * Bug (medido 2026-08-19, F1 do handoff Superadmin F3): a migration de criação
 * (2018_06_28_182803) declarou `enum('approved','waiting','declined')` e NENHUMA
 * migration posterior a alterou (varredura contada em todas as migrations do
 * repo: zero hits). Mas o SubscriptionLifecycleService — introduzido depois —
 * grava dois valores que esse enum não aceita:
 *
 *   - SubscriptionLifecycleService.php:96   $subscription->status = 'expired'
 *   - SubscriptionLifecycleService.php:123  $subscription->status = 'cancelled'
 *
 * Em MySQL strict mode o UPDATE falha (SQLSTATE 01000 / erro 1265 "Data truncated
 * for column 'status'"); em non-strict, grava string vazia silenciosamente. Nos
 * dois casos a assinatura NÃO fica no estado que o service afirma ter posto — e
 * `findOverdueApproved()` (o sweep de cron, linha 146) continua devolvendo a
 * mesma linha pra sempre, porque ela nunca sai de `approved`.
 *
 * Os outros três writes do módulo já estavam dentro do enum e seguem intactos:
 * PesaPalController:25/32 (`approved`/`waiting`), SubscriptionController:300
 * (`waiting`) e OnCobrancaVencidaBloqueaSubscription:63 (`declined`).
 *
 * NÃO inclui `waiting_approval` de propósito: o service compara com esse valor
 * em :48, mas ninguém o grava e o enum nunca o aceitou — logo ele não pode
 * existir no banco, e o branch é provadamente morto. Ampliar o enum pra
 * acomodar código morto esconderia o defeito em vez de expor. Tratar em PR
 * separado (1 PR = 1 intent).
 *
 * Só AMPLIA. O down() recusa estreitar se já houver linhas nos estados novos —
 * estreitar truncaria assinatura real (dado de cobrança).
 *
 * @see Modules/Superadmin/Services/SubscriptionLifecycleService.php
 * @see Modules/Superadmin/Database/Migrations/2018_06_28_182803_create_subscriptions_table.php
 * @see Modules/Superadmin/Tests/Feature/SubscriptionLifecycleServiceTest.php
 */
return new class extends Migration
{
    private const ENUM_WIDE = "'approved','waiting','declined','expired','cancelled'";

    private const ENUM_NARROW = "'approved','waiting','declined'";

    /** Estados que existem no enum ampliado e não no original. */
    private const ADDED = ['expired', 'cancelled'];

    public function up(): void
    {
        // MySQL-only: SQLite (lane de teste reduzida) não tem MODIFY COLUMN e não
        // enforça ENUM — o bug só existe no MySQL. Convenção idêntica à
        // widen_arquivos_audit_log_action_enum (2026_07_02_000001).
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }
        if (! Schema::hasTable('subscriptions')) {
            return;
        }

        // Idempotente: re-rodar com o mesmo alvo é no-op para o MySQL.
        DB::statement(
            'ALTER TABLE subscriptions MODIFY COLUMN status ENUM(' . self::ENUM_WIDE . ") NOT NULL DEFAULT 'waiting'"
        );
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }
        if (! Schema::hasTable('subscriptions')) {
            return;
        }

        // Guarda: estreitar com linhas nos estados novos truncaria assinatura
        // real (uma cancelada voltaria a valer). Recusa explícita > perda silenciosa.
        $presos = DB::table('subscriptions')
            ->whereIn('status', self::ADDED)
            ->count();

        if ($presos > 0) {
            throw new \RuntimeException(
                "Reversão bloqueada: {$presos} assinatura(s) em 'expired'/'cancelled'. "
                . 'Estreitar o enum truncaria o status de cobrança real.'
            );
        }

        DB::statement(
            'ALTER TABLE subscriptions MODIFY COLUMN status ENUM(' . self::ENUM_NARROW . ") NOT NULL DEFAULT 'waiting'"
        );
    }
};
