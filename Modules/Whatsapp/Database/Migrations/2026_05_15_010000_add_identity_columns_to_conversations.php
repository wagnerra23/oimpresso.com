<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PR1 — Schema 3-identifiers (lid + phone_e164 + bsuid)
 *
 * Estudo protocol-level WhatsApp Multi-Device 2026
 * ([memory/sessions/2026-05-15-estudo-whatsapp-protocol-vs-oimpresso.md])
 * revelou 3 IDs distintos que WhatsApp expõe a libs terceiras:
 *
 *  - **LID** (`<random>@lid`) — anonymized account-level ID per-USER
 *    (NÃO per-chat). Permanente. Aparece em click-to-chat, click-to-ads,
 *    grupos com "hide phone numbers", first contact moderno.
 *  - **phone_e164** (`+E.164`) — telefone real do user no formato
 *    canônico. Pode chegar via `senderPn` (Baileys 6.8+) OU resolução
 *    explicita via tabela `whatsapp_lid_pn_map`.
 *  - **BSUID** (`user_id` Cloud API) — identificador Meta-oficial
 *    business-scoped per user × business. Disponível em Cloud API
 *    webhooks desde 31-mar-2026. Substitui PN pra users com username
 *    (jun/2026 GA).
 *
 * Hoje `conversations.customer_external_id` é string única (ora LID, ora
 * phone, dependendo da origem da msg). Schema multi-identifier permite:
 *
 *  1. Persistir 3 IDs distintos quando disponíveis (sem perder dados).
 *  2. Lookup direto por qualquer um dos 3 (index per-coluna).
 *  3. Preparar migração futura pra Cloud API oficial (BSUID nativo).
 *  4. Forensics — auditoria cross-contact debugging.
 *
 * Multi-tenant Tier 0 (ADR 0093): índices SEMPRE compostos
 * `(business_id, <coluna>)` pra preservar global scope.
 *
 * Idempotente — `Schema::hasColumn` e `Schema::hasTable` antes de cada op.
 * Aplica tanto na tabela legacy `conversations` (omnichannel ADR 0135 —
 * prod biz=1 hoje) quanto na pré-existente `whatsapp_conversations`
 * (zerada hoje, pré-omnichannel). Ambas sobrevivem em paralelo até a
 * migração final do PR 5 (data migration).
 *
 * Nomes de índice via `substr(..., 0, 60)` por proibição MySQL identifier
 * 64-chars (CLAUDE.md proibições §Código).
 *
 * @see memory/sessions/2026-05-15-estudo-whatsapp-protocol-vs-oimpresso.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0135-omnichannel-inbox-arquitetura.md
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['conversations', 'whatsapp_conversations'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'lid')) {
                    $t->string('lid', 100)->nullable()->after('customer_external_id');
                    $t->index(
                        ['business_id', 'lid'],
                        substr($table.'_biz_lid_idx', 0, 60),
                    );
                }
            });

            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'phone_e164')) {
                    $t->string('phone_e164', 30)->nullable()->after('lid');
                    $t->index(
                        ['business_id', 'phone_e164'],
                        substr($table.'_biz_phone_idx', 0, 60),
                    );
                }
            });

            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'bsuid')) {
                    $t->string('bsuid', 100)->nullable()->after('phone_e164');
                    $t->index(
                        ['business_id', 'bsuid'],
                        substr($table.'_biz_bsuid_idx', 0, 60),
                    );
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['conversations', 'whatsapp_conversations'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                if (Schema::hasColumn($table, 'bsuid')) {
                    $t->dropIndex(substr($table.'_biz_bsuid_idx', 0, 60));
                    $t->dropColumn('bsuid');
                }
            });

            Schema::table($table, function (Blueprint $t) use ($table) {
                if (Schema::hasColumn($table, 'phone_e164')) {
                    $t->dropIndex(substr($table.'_biz_phone_idx', 0, 60));
                    $t->dropColumn('phone_e164');
                }
            });

            Schema::table($table, function (Blueprint $t) use ($table) {
                if (Schema::hasColumn($table, 'lid')) {
                    $t->dropIndex(substr($table.'_biz_lid_idx', 0, 60));
                    $t->dropColumn('lid');
                }
            });
        }
    }
};
