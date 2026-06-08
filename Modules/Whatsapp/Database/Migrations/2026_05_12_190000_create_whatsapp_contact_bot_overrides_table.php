<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * whatsapp_contact_bot_overrides — toggle bot per-contato (US-WA-077, ADR 0142 §3c).
 *
 * Atendente escreve em nota interna `/config bot=off` → cria row aqui pra
 * sobrescrever o `bot_enabled` global do business/canal. Engine de bot
 * (DispatchToJanaBot) consulta override ANTES do flag global — se override
 * existe, vence. Se NÃO existe, fallback pro config do canal/business.
 *
 * UNIQUE (business_id, contact_id) — 1 só override por contato. `/config`
 * subsequente faz updateOrCreate (idempotente: 1 row por contato sempre).
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) — `business_id` global scope
 * via trait HasBusinessScope no Model. UNIQUE composto inclui business_id
 * defense-in-depth contra colisão cross-tenant.
 *
 * Migration idempotente (Schema::hasTable guard) — pode rodar 2x sem
 * quebrar (refletindo padrão do resto do módulo Whatsapp).
 *
 * @see memory/decisions/0142-notas-internas-sinal-treino-jana.md §3c
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-077
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_contact_bot_overrides')) {
            return; // idempotente
        }

        Schema::create('whatsapp_contact_bot_overrides', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('contact_id')
                ->comment('FK contacts UltimatePOS — sem FK formal (core table)');
            $table->boolean('bot_enabled')
                ->comment('Override do flag global do canal/business — true reativa, false desativa');
            $table->unsignedInteger('set_by_user_id')
                ->comment('Atendente que executou /config (audit)');
            $table->text('reason')->nullable()
                ->comment('Razão opcional pra audit (ex: "cliente reclamou que bot é chato")');
            $table->timestamp('set_at')
                ->comment('Quando o override foi (re)definido');
            $table->timestamps();

            // UNIQUE composto inclui business_id pra defense-in-depth Tier 0:
            // mesmo se um bug fizesse contact_id colidir entre tenants, o
            // par (business_id, contact_id) ainda é único.
            $table->unique(
                ['business_id', 'contact_id'],
                'wcbo_biz_contact_unq'
            );
            $table->index(
                ['set_by_user_id', 'set_at'],
                'wcbo_set_by_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_contact_bot_overrides');
    }
};
