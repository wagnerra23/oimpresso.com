<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase A1 (Swimm-like doc↔código na KB) — persiste o veredito do drift detector.
 *
 * Hoje `kb:drift-detector` (KbDriftDetectorCommand) calcula quais artigos citam
 * arquivos deletados/movidos no git e SÓ loga no canal copiloto-ai. O sinal morre.
 * Esta coluna permite persistir o veredito por nó pra surfacar na KB (HealthPanel
 * + NodeReader — Fase A2).
 *
 * Formato do JSON:
 *   null                                → nunca checado OU sem drift atual
 *   {"checked_at": "<iso8601>", "refs": [{"path": "...", "drift_type": "reference_deleted_path"}]}
 *
 * Aditivo + nullable — zero risco a dado existente. Escrita é raw (DB::table) no
 * command, fora do KbNodeObserver/activity-log (não polui audit LGPD).
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('kb_nodes')) {
            return;
        }

        if (! Schema::hasColumn('kb_nodes', 'code_drift_state')) {
            Schema::table('kb_nodes', function (Blueprint $table) {
                $table->json('code_drift_state')->nullable()
                    ->comment('Fase A1 — veredito kb:drift-detector: {checked_at, refs:[{path, drift_type}]}. null = sem drift/nunca checado.');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('kb_nodes', 'code_drift_state')) {
            Schema::table('kb_nodes', function (Blueprint $table) {
                $table->dropColumn('code_drift_state');
            });
        }
    }
};
