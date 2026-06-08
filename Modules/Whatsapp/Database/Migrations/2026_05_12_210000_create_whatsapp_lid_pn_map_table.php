<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * whatsapp_lid_pn_map — tabela ponte LID (Linked ID Multi-Device) → phone E.164.
 *
 * Wagner observou em prod biz=1 (2026-05-12): conversa com `customer_phone =
 * "+519691546333945"` (15 dígitos sem prefixo BR) — é um LID, identificador
 * anti-spam do WhatsApp Multi-Device entregue como remoteJid="X@lid" quando
 * cliente fala via Click-to-Chat / Status / Ads.
 *
 * Baileys 6.7.9 (atual prod) NÃO mapeia LID → phone real. Versão 7.x já tem
 * Alt JID nativo mas migração é US separada. Workaround custom:
 *
 *  1. Webhook persiste `(lid, phone)` quando WhatsApp envia `senderPn` real
 *     ao lado do `remoteJid@lid` (acontece esporadicamente, varia conforme
 *     versão WA app cliente).
 *  2. Próxima msg do mesmo LID → resolve cache → exibe phone real na UI.
 *
 * Tier 0 IRREVOGÁVEL (ADR 0093): `business_id` global scope + UNIQUE
 * `(business_id, lid)` impede vazamento entre tenants. Mesma LID em
 * business diferente = row independente.
 *
 * Append-update — `phone_e164` pode ser NULL inicialmente (descoberta
 * deferida); `record()` preenche quando descobre via `senderPn`.
 *
 * @see Modules\Whatsapp\Services\Contacts\LidPhoneResolver
 * @see memory/decisions/0135-omnichannel-inbox-arquitetura.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_lid_pn_map', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')
                ->comment('ADR 0093 Tier 0 — global scope obrigatório');
            $table->string('lid', 100)
                ->comment('ex: "5196915463394@lid" cru OU "+519691546333945" se normalizado pelo controller');
            $table->string('phone_e164', 32)->nullable()
                ->comment('null enquanto não descoberto — preenche quando WA envia senderPn');
            $table->enum('source', ['webhook_senderPn', 'manual', 'baileys_lookup'])
                ->default('webhook_senderPn')
                ->comment('rastreia origem do mapping pra auditoria + decidir confiança');
            $table->timestamp('first_seen_at')->useCurrent();
            $table->timestamp('last_seen_at')->useCurrent();
            $table->timestamps();

            $table->unique(['business_id', 'lid'], 'wa_lid_pn_business_lid_uniq');
            $table->index(['business_id', 'phone_e164'], 'wa_lid_pn_business_phone_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_lid_pn_map');
    }
};
