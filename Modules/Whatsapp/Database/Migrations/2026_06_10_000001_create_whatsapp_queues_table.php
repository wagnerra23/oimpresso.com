<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-WA-301 (ADR 0267) — filas de atendimento persistidas em DB.
 *
 * Substitui `config('whatsapp.queues')` estático (2 filas hardcoded) por
 * tabela multi-tenant editável no painel "Filas" da Caixa Unificada V4.
 * Seed lazy idempotente a partir do config no primeiro acesso por business
 * (CaixaUnificadaController::ensureDefaultQueues — pattern ensureDefaultTags).
 *
 * `dist` e `members` são SÓ persistência nesta fase (roteamento automático
 * round-robin/sticky é US futura — TODO honesto anti M-AP-2).
 *
 * Idempotente: hasTable guard — pode rodar 2× sem dropar.
 *
 * @see memory/decisions/0267-whatsapp-queues-tabela-filas-atendimento.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_queues')) {
            return;
        }

        Schema::create('whatsapp_queues', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->string('slug', 40);
            $table->string('label', 80);
            $table->unsignedSmallInteger('hue')->default(220)
                ->comment('0-360 OKLCH — chips/border-left da lista V4');
            $table->unsignedInteger('sla_minutes')->nullable()
                ->comment('SLA alvo 1a resposta; null = sem SLA');
            $table->string('dist', 20)->default('manual')
                ->comment('round_robin|sticky|manual — persistencia only nesta fase (ADR 0267)');
            $table->json('trigger_tags')
                ->comment('slugs de tags que disparam a fila (heuristica OR)');
            $table->json('members')
                ->comment('user_ids membros — persistencia only nesta fase (ADR 0267)');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['business_id', 'slug'], 'wq_biz_slug_uniq');
            $table->index('business_id', 'wq_biz_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_queues');
    }
};
