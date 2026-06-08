<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-WA-063 — Tags / classificador de conversa.
 *
 * Wagner 2026-05-11: "Opção de inserir a conversa em um grupo/tag classificador."
 *
 * Inspirado em admin_chat_empresarial.html aba Estrutura ("Canais por assunto" —
 * nomenclatura padronizada). Adaptado pra atendimento WhatsApp externo:
 * em vez de canal-por-assunto (interno Slack-like), usamos tags-por-conversa
 * (uma conversa de cliente pode ter múltiplas tags: "Vendas" + "Repair-OS").
 *
 * Schema:
 *  - `whatsapp_tags`: catálogo de tags por business_id (Tier 0 ADR 0093)
 *  - `whatsapp_conversation_tags`: pivot many-to-many (conversation_id + tag_id)
 *
 * Naming: prefixado `whatsapp_` mesmo morando em schema omnichannel novo, pra
 * evitar colisão com `mcp_tasks.labels` (que é JSON column) ou com futuro
 * `crm_tags`. Refactor pra `tags` genérica fica em ADR futuro se outros módulos
 * adotarem (Repair OS já tem `repair_categories`, ProjectMgmt tem labels JSON).
 *
 * @see memory/decisions/0135-omnichannel-inbox-arquitetura.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_tags', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->string('slug', 40)->comment('slug imutável pra seeds reseed (ex: vendas, suporte)');
            $table->string('label', 80);
            $table->string('color', 20)->default('slate')->comment('Tailwind palette key: blue|green|amber|red|slate|purple|emerald|rose');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            // UNIQUE (business_id, slug) — mesma tag não pode duplicar no business
            $table->unique(['business_id', 'slug'], 'wa_tags_biz_slug_uniq');
        });

        Schema::create('whatsapp_conversation_tags', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('tag_id');
            $table->timestamp('created_at')->useCurrent();
            // updated_at obrigatório pra relation `belongsToMany->withTimestamps()`
            // — adicionado pós-hotfix US-WA-091 (laravel.log 18:14 prod broke).
            // Pest tests anteriores tinham a coluna no schema mas a migration
            // original não — drift entre intenção e migração real.
            $table->timestamp('updated_at')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable()->comment('atendente que aplicou a tag');

            // UNIQUE — mesma tag não pode ser aplicada 2x na mesma conv
            $table->unique(['conversation_id', 'tag_id'], 'wa_conv_tags_uniq');
            $table->index('tag_id', 'wa_conv_tags_tag_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conversation_tags');
        Schema::dropIfExists('whatsapp_tags');
    }
};
