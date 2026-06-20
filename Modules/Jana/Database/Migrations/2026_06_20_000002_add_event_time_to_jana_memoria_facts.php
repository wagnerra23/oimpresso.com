<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0295 (slice 1) — bi-temporal event-time na memoria Jana.
 *
 * SO ADITIVA: event_valid_from/until (event-time, quando o fato valeu NO MUNDO,
 * distinto do system-time valid_from/until) + supersedes_id (link explicito pro
 * fato que este substitui). Tudo nullable — fatos legados ficam "sempre event-
 * validos" (sem backfill retroativo, nao-decisao de 0074). Nenhuma mudanca de
 * comportamento neste slice; popular as colunas e slice 2/3.
 *
 * Tabela jana_memoria_facts (rename ADR 0092, VIEW legacy) — Schema::hasTable
 * guard pra nao tentar ALTER na VIEW / em ambiente sem a tabela (CI SQLite).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jana_memoria_facts')) {
            return;
        }

        Schema::table('jana_memoria_facts', function (Blueprint $table) {
            if (! Schema::hasColumn('jana_memoria_facts', 'event_valid_from')) {
                $table->dateTime('event_valid_from')->nullable()->after('valid_until')
                    ->comment('Event-time: quando o fato passou a valer no mundo (ADR 0295). Null = desde sempre');
            }
            if (! Schema::hasColumn('jana_memoria_facts', 'event_valid_until')) {
                $table->dateTime('event_valid_until')->nullable()->after('event_valid_from')
                    ->comment('Event-time: quando o fato deixou de valer no mundo (ADR 0295). Null = ainda vale');
            }
            if (! Schema::hasColumn('jana_memoria_facts', 'supersedes_id')) {
                $table->unsignedBigInteger('supersedes_id')->nullable()->after('event_valid_until')
                    ->comment('Link explicito pro fato que este substitui (ADR 0295). FK logica — app enforce');
            }

            $table->index(['event_valid_from', 'event_valid_until'], 'jmf_event_validity_idx');
            $table->index('supersedes_id', 'jmf_supersedes_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('jana_memoria_facts')) {
            return;
        }

        Schema::table('jana_memoria_facts', function (Blueprint $table) {
            $table->dropIndex('jmf_event_validity_idx');
            $table->dropIndex('jmf_supersedes_idx');
            $table->dropColumn(['supersedes_id', 'event_valid_until', 'event_valid_from']);
        });
    }
};
