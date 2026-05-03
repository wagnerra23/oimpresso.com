<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ARQ-0008 — coluna dismissed_at: Wagner remove items não-acionáveis do inbox
// (ex: items bloqueados pelo firewall que já cumpriram seu papel).
// Não muda outcome — só sinaliza "Wagner viu e dispensou da fila".
class AddDismissedAtToDualBrainDecisions extends Migration
{
    public function up(): void
    {
        Schema::table('mcp_dual_brain_decisions', function (Blueprint $table) {
            $table->timestamp('dismissed_at')->nullable()->after('resolved_by');
            $table->index('dismissed_at', 'idx_dbd_dismissed');
        });
    }

    public function down(): void
    {
        Schema::table('mcp_dual_brain_decisions', function (Blueprint $table) {
            $table->dropIndex('idx_dbd_dismissed');
            $table->dropColumn('dismissed_at');
        });
    }
}
