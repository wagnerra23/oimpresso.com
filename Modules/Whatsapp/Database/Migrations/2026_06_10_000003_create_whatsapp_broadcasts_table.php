<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-WA-306 (ADR 0268) — campanha broadcast cross-canal (FASE 1: modelo +
 * pre-flight + draft; disparo em massa é fase 2 com gate [W]).
 *
 * `status` só grava 'draft' nesta fase — transições dispatching/done/failed
 * chegam com o Job rate-limited da fase 2 (anti M-AP-2: enum existe, motor não
 * finge rodar).
 *
 * Inclui também a coluna de consentimento LGPD `whatsapp_opt_in_at` em
 * `contacts` (NULL = sem opt-in = fora de qualquer broadcast).
 *
 * Idempotente: hasTable/hasColumn guards.
 *
 * @see memory/decisions/0268-whatsapp-broadcasts-campanha-scaffold.md
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_broadcasts')) {
            Schema::create('whatsapp_broadcasts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id');
                $table->unsignedBigInteger('channel_id');
                $table->unsignedInteger('created_by_user_id');
                $table->string('kind', 10)->default('freeform')
                    ->comment('freeform|template');
                $table->string('template_name', 64)->nullable();
                $table->text('body')->nullable();
                $table->string('status', 20)->default('draft')
                    ->comment('draft|dispatching|done|failed — fase 1 so grava draft (ADR 0268)');
                $table->json('audience_snapshot')
                    ->comment('contagens do pre-flight congeladas no save (auditoria)');
                $table->json('recipient_conversation_ids')
                    ->comment('conversas elegiveis (opt-in LGPD) no momento do pre-flight');
                $table->timestamp('dispatched_at')->nullable();
                $table->timestamps();

                $table->index(['business_id', 'status'], 'wb_biz_status_idx');
            });
        }

        if (Schema::hasTable('contacts') && ! Schema::hasColumn('contacts', 'whatsapp_opt_in_at')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->timestamp('whatsapp_opt_in_at')->nullable()
                    ->comment('LGPD: consentimento marketing WhatsApp; NULL = fora de broadcast (ADR 0268)');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_broadcasts');
        if (Schema::hasTable('contacts') && Schema::hasColumn('contacts', 'whatsapp_opt_in_at')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->dropColumn('whatsapp_opt_in_at');
            });
        }
    }
};
