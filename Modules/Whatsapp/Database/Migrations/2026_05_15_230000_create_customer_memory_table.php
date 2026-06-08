<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-WA-VOZ-001 — Customer Memory (perfil persistente do cliente final).
 *
 * Wagner 2026-05-15: "onde vai ficar as memórias? estruture. vai precisar
 * achar o telefone e o cliente no banco de dados do servidor."
 *
 * Single source of truth da MEMÓRIA DO CLIENTE FINAL (a pessoa do outro
 * lado do WhatsApp) — NÃO confundir com memória do usuário SaaS (Wagner/
 * Maiara/Luiz) que vive em `copiloto_memoria_facts` (Jana, ADR 0036).
 *
 * Relação opcional 1:N com `contacts` (UltimatePOS CRM legacy) via
 * `contact_id` — resolvida por `ConversationContactLinker` (já existe).
 * Customer pode existir SEM contact_id (cliente que nunca foi cadastrado
 * no CRM mas mandou msg).
 *
 * Idempotente — `if (! Schema::hasTable)` skip.
 *
 * Tier 0 multi-tenant ([ADR 0093](../../../decisions/0093-multi-tenant-isolation-tier-0.md)):
 * `business_id` NOT NULL + FK + UNIQUE composto + global scope no Eloquent.
 *
 * Estado-da-arte 2026 (Decagon User Memory + Gladly Customer Profile +
 * Front Customer Context) — schema cobre Customer 360 + LGPD-ready + recall
 * pattern. Detalhes em
 * `memory/sessions/2026-05-15-arte-atendimento-omnichannel-memoria-cliente.md`.
 *
 * @see Modules/Whatsapp/Entities/CustomerMemory.php
 * @see Modules/Whatsapp/Services/CustomerMemory/CustomerMemoryRebuilder.php
 * @see memory/sessions/2026-05-15-analise-voz-cliente-suporte-biz1.md §3-§4
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_memory')) {
            return;
        }

        Schema::create('customer_memory', function (Blueprint $table): void {
            $table->bigIncrements('id');

            // Tier 0 — multi-tenant scope
            $table->unsignedInteger('business_id');

            // Identidade do cliente (sempre presente)
            $table->string('customer_external_id', 40)
                ->comment('E.164 sem + (ex: 5548999872822) — chave do cliente no canal');
            $table->string('phone_normalized', 20)->nullable()
                ->comment('Só dígitos do customer_external_id — match rápido contra contacts.mobile');

            // Relação opcional com Contact CRM (UltimatePOS) — pode ser NULL
            // se cliente não cadastrado. Match feito por ConversationContactLinker.
            $table->unsignedInteger('contact_id')->nullable();
            $table->string('identity_match_method', 24)->nullable()
                ->comment('exact|suffix_8|manual|ambiguous_picked_first|unknown');
            $table->decimal('identity_match_confidence', 3, 2)->nullable()
                ->comment('0.00..1.00 — 1.0=unique match, 0.5=ambíguo, NULL=não tentado');
            $table->timestamp('identity_match_at')->nullable();

            // Display name denormalizado (evita JOIN contacts em cada listing).
            // Atualiza quando contact_id muda OU pushName muda significativamente.
            $table->string('display_name', 120)->nullable();

            // Stats agregados (Job daily rebuild OU listener real-time)
            $table->unsignedInteger('n_conversations')->default(0);
            $table->unsignedInteger('n_msgs_inbound')->default(0);
            $table->unsignedInteger('n_msgs_outbound')->default(0);
            $table->timestamp('first_interaction_at')->nullable();
            $table->timestamp('last_interaction_at')->nullable();

            // Inferências IA — NULL até Onda 3 ativar (Jana enrichment mensal)
            $table->json('temas_recorrentes')->nullable()
                ->comment('["nfe","boleto","atualizacao_bug"] — top 3-5 temas histórico 90d');
            $table->decimal('sentimento_score', 3, 2)->nullable()
                ->comment('-1.00..+1.00 — média ponderada sentimento msgs inbound 90d');
            $table->decimal('churn_risk_score', 3, 2)->nullable()
                ->comment('0.00..1.00 — heurística + ML futuro');
            $table->json('comunicacao_preferida')->nullable()
                ->comment('{"hora_pico":"14-17h","canal":"whatsapp","tom":"formal"}');

            // Memória qualitativa (manual + Jana future via /lembrar slash command)
            $table->text('notas_jana')->nullable()
                ->comment('Notas livres acumuladas — max ~2KB');
            $table->timestamp('notas_atualizada_em')->nullable();

            // Flags operacionais — VIP, frágil, churn_warned, etc
            $table->json('flags')->nullable()
                ->comment('[{"tipo":"vip","since":"2026-05-10","motivo":"alto LTV"}]');

            // LGPD — denormaliza consent + erasure tracking
            $table->string('consent_status', 16)->nullable()
                ->comment('given|withdrawn|unknown — cache de contacts.whatsapp_consent');
            $table->timestamp('erasure_requested_at')->nullable()
                ->comment('LGPD Art. 18 — soft delete; purge job apaga após retention period');

            // Tracking rebuild (debugging + monitoring)
            $table->timestamp('last_rebuilt_at')->nullable();
            $table->string('rebuilt_via', 24)->nullable()
                ->comment('backfill|cron_daily|listener|manual|webhook');

            $table->timestamps();

            // Constraints + índices
            $table->unique(['business_id', 'customer_external_id'], 'customer_memory_biz_ext_uniq');
            $table->index(['business_id', 'contact_id'], 'customer_memory_biz_contact_idx');
            $table->index(['business_id', 'last_interaction_at'], 'customer_memory_biz_lastint_idx');
            $table->index(['business_id', 'churn_risk_score'], 'customer_memory_biz_churn_idx');
            $table->index('phone_normalized', 'customer_memory_phone_idx');
        });

        // FK business_id → business.id (CASCADE on delete — se business sumir, memória vai junto)
        // Defensive: se schema legacy não tiver `business`, pula FK
        if (Schema::hasTable('business')) {
            Schema::table('customer_memory', function (Blueprint $table): void {
                $table->foreign('business_id', 'customer_memory_business_fk')
                    ->references('id')->on('business')
                    ->onDelete('cascade');
            });
        }

        // FK contact_id → contacts.id (SET NULL on delete — preserva memória mesmo se Contact apagado)
        if (Schema::hasTable('contacts')) {
            Schema::table('customer_memory', function (Blueprint $table): void {
                $table->foreign('contact_id', 'customer_memory_contact_fk')
                    ->references('id')->on('contacts')
                    ->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_memory');
    }
};
