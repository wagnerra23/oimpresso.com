<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona índice em `channels.display_identifier` pra acelerar a validação
 * cross-business introduzida em PR #814 (`ChannelRequest::withValidator()`).
 *
 * Por que: o validation faz query `withoutGlobalScopes()->whereIn('type', [...])
 * ->where('display_identifier', $X)->where('id', '!=', $self)->exists()` a cada
 * save de Channel. Sem índice em `display_identifier`, conforme `channels`
 * cresce (1k+ rows em 6 meses), o full-scan vira P1 lento. Esta migration
 * cria índice non-UNIQUE — a unicidade fica gerenciada no app layer
 * (multi-tenant Tier 0 ADR 0093 impede UNIQUE constraint cross-business
 * sem `business_id` no índice composto, mas precisamos varrer cross-business).
 *
 * Índice composto `(display_identifier, type)` cobre os dois usos:
 *   1. ChannelRequest validation (cross-business unique check)
 *   2. Lookups admin "qual canal tem este telefone?"
 *
 * Sem UP/DOWN destrutivo. Migration idempotente via `Schema::hasIndex()`.
 *
 * @see Modules/Whatsapp/Http/Requests/ChannelRequest.php PR #814
 * @see memory/sessions/2026-05-13-whatsapp-incident-zombie-banned-loop.md §Gap B
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('channels')) {
            return; // defensive: módulo Whatsapp não-instalado
        }

        $indexName = 'channels_display_identifier_type_idx';

        // Idempotência via information_schema query (Schema::hasIndex inexistente em Laravel)
        $exists = collect(\DB::select("SHOW INDEX FROM channels WHERE Key_name = ?", [$indexName]))->isNotEmpty();
        if ($exists) {
            return;
        }

        Schema::table('channels', function (Blueprint $table): void {
            $table->index(['display_identifier', 'type'], 'channels_display_identifier_type_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('channels')) {
            return;
        }

        $indexName = 'channels_display_identifier_type_idx';
        $exists = collect(\DB::select("SHOW INDEX FROM channels WHERE Key_name = ?", [$indexName]))->isNotEmpty();
        if (! $exists) {
            return;
        }

        Schema::table('channels', function (Blueprint $table): void {
            $table->dropIndex('channels_display_identifier_type_idx');
        });
    }
};
