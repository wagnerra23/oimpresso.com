<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-WA-305 (charter §4 · consequência prevista no ADR 0267) — override manual
 * de fila por conversa.
 *
 * Hoje a fila é DERIVADA read-only (heurística trigger_tags → fila). Wagner
 * quer mover conversa de fila SEM re-tagar: `queue_override` (slug de
 * `whatsapp_queues`) vence a heurística quando preenchido; NULL = volta pra
 * derivação automática.
 *
 * String slug (não FK id) de propósito: filas são soft-config por business
 * (slug estável, ADR 0267); deletar fila não quebra a conversa — slug órfão
 * cai no fallback heurística no Controller.
 *
 * Idempotente: hasColumn guard.
 *
 * @see memory/decisions/0267-whatsapp-queues-tabela-filas-atendimento.md
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversations') || Schema::hasColumn('conversations', 'queue_override')) {
            return;
        }

        Schema::table('conversations', function (Blueprint $table) {
            $table->string('queue_override', 40)->nullable()->after('assigned_user_id')
                ->comment('US-WA-305: slug de whatsapp_queues que vence a heuristica tag→fila; NULL = automatica');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('conversations') && Schema::hasColumn('conversations', 'queue_override')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->dropColumn('queue_override');
            });
        }
    }
};
