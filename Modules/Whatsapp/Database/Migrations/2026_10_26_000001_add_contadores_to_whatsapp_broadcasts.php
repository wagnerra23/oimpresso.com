<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-WA-322 (ADR 0268 fase 2) — contadores do disparo real da campanha.
 *
 * `total` é congelado quando o disparo começa; cada envio soma em `enviados`, `falhas` ou
 * `pulados` (opt-in revogado, conversa bloqueada, fora da janela de 24h). Quando a soma chega
 * ao total, a campanha vira `done`. Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_broadcasts') || Schema::hasColumn('whatsapp_broadcasts', 'total')) {
            return;
        }
        Schema::table('whatsapp_broadcasts', function (Blueprint $table) {
            $table->unsignedInteger('total')->default(0)->after('recipient_conversation_ids');
            $table->unsignedInteger('enviados')->default(0)->after('total');
            $table->unsignedInteger('falhas')->default(0)->after('enviados');
            $table->unsignedInteger('pulados')->default(0)->after('falhas');
            $table->timestamp('concluido_em')->nullable()->after('dispatched_at');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('whatsapp_broadcasts') && Schema::hasColumn('whatsapp_broadcasts', 'total')) {
            Schema::table('whatsapp_broadcasts', function (Blueprint $table) {
                $table->dropColumn(['total', 'enviados', 'falhas', 'pulados', 'concluido_em']);
            });
        }
    }
};
