<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-WA-048 — Macros (superset de quick reply + ações).
 *
 * Conceito (Chatwoot pattern, gap P1 #6+#12 em
 * memory/requisitos/Whatsapp/COMPARATIVO-MERCADO-2026-05-12.md):
 *
 *   Macro = template estendido que pode ter ações múltiplas
 *           (send reply + tag + status + assign). Funde "quick replies"
 *           e "automation actions" num único objeto operacional.
 *
 * UI (em PR seguinte): dropdown `/` no composer da Inbox, busca live
 * por shortcut ou label, click executa send + actions JSON em ordem.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) — `business_id` global
 * scope via trait `HasBusinessScope`. UNIQUE (business_id, shortcut)
 * permite mesmo shortcut em businesses diferentes.
 *
 * @see memory/requisitos/Whatsapp/COMPARATIVO-MERCADO-2026-05-12.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('macros', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->string('label', 80)->comment('rotulo visivel no dropdown (ex: Pedir CNPJ)');
            $table->string('shortcut', 30)->nullable()->comment('atalho slash opcional (ex: /cnpj)');
            $table->text('body')->comment('corpo da mensagem, suporta {{vars}} em PR futuro');
            $table->json('actions_json')->nullable()->comment('[{"type":"add_tag","tag_id":3},{"type":"set_status","status":"awaiting_human"}]');
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedInteger('used_count')->default(0)->comment('contador de uso (analytics top-N)');
            $table->timestamps();

            $table->index(['business_id'], 'macros_business_idx');
            // UNIQUE shortcut por business — mesmo /cnpj entre biz=1 e biz=99 OK
            $table->unique(['business_id', 'shortcut'], 'macros_business_shortcut_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('macros');
    }
};
