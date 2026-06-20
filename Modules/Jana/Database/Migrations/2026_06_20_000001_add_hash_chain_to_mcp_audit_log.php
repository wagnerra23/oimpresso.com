<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0294 — hash-chain SHA-256 tamper-evident no mcp_audit_log.
 *
 * SO ADITIVA (hash, hash_anterior). Append-only compativel: NUNCA backfillar
 * linhas legadas por UPDATE — bateria no trigger trg_mcp_audit_log_no_update
 * (ADR 0084). Linhas pre-0294 ficam hash=null; AuditChainService tolera o
 * prefixo legado e ancora a cadeia na primeira linha com hash.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mcp_audit_log')) {
            return; // ambiente sem a tabela (CI SQLite sem migrate) — no-op gracioso
        }

        Schema::table('mcp_audit_log', function (Blueprint $table) {
            if (! Schema::hasColumn('mcp_audit_log', 'hash_anterior')) {
                $table->char('hash_anterior', 64)->nullable()->after('payload_summary')
                    ->comment('SHA-256 da linha N-1 na cadeia global (ADR 0294); null no prefixo legado');
            }
            if (! Schema::hasColumn('mcp_audit_log', 'hash')) {
                $table->char('hash', 64)->nullable()->after('hash_anterior')
                    ->comment('SHA-256(payloadCanonico|hash_anterior) — tamper-evidence (ADR 0294)');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mcp_audit_log')) {
            return;
        }

        Schema::table('mcp_audit_log', function (Blueprint $table) {
            if (Schema::hasColumn('mcp_audit_log', 'hash')) {
                $table->dropColumn('hash');
            }
            if (Schema::hasColumn('mcp_audit_log', 'hash_anterior')) {
                $table->dropColumn('hash_anterior');
            }
        });
    }
};
