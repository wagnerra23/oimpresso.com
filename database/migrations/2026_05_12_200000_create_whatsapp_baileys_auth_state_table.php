<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P0 #1 — `whatsapp_baileys_auth_state` substitui `useMultiFileAuthState` em prod.
 *
 * Baileys upstream literal: "Don't ever use the useMultiFileAuthState in production".
 * Volume FS no CT 100 sofria corrupção → session revogada → QR fresh (perda de chip).
 * Esta tabela armazena `creds` + Signal keys cifrados AES-256-CBC pela APP_KEY Laravel,
 * lidos/escritos pelo daemon Node TS (`Modules/Whatsapp/daemon-node/src/baileys/mysqlAuthState.ts`).
 *
 * Schema:
 *  - `instance_id`     varchar(100) índice — UUID derivado business (ex 'ch-deadbeef...')
 *  - `key_id`          varchar(200) — 'creds' | '<signal-type>-<id>' | 'app-state-sync-key-<id>'
 *  - `value_encrypted` mediumtext — IV(16B) + AES-256-CBC ciphertext, base64-encoded
 *  - UNIQUE (`instance_id`, `key_id`)
 *
 * Multi-tenant: NÃO usa `business_id` global scope porque daemon Node não tem session Laravel.
 * Filtro por `instance_id` é suficiente — UUID derivado business garante unicidade cross-tenant
 * (Hostinger gera + persiste em `whatsapp_channels.instance_uuid`).
 *
 * Idempotente: hasTable guard permite replay sem quebrar prod (mesma postura US-COPI-092).
 *
 * Refs: ADR 0093 multi-tenant Tier 0, ADR 0096 driver Baileys,
 * `Modules/Whatsapp/daemon-node/src/baileys/mysqlAuthState.ts` consume.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_baileys_auth_state')) {
            return;
        }

        Schema::create('whatsapp_baileys_auth_state', function (Blueprint $table) {
            $table->id();
            $table->string('instance_id', 100);
            $table->string('key_id', 200);
            $table->mediumText('value_encrypted');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['instance_id', 'key_id'], 'wa_auth_state_uniq');
            $table->index('instance_id', 'wa_auth_state_instance_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_baileys_auth_state');
    }
};
