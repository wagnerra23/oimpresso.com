<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona soft-delete (`deleted_at`) a `mcp_tokens`.
 *
 * BUG real (triagem SDD nightly 20260614): McpTokenIssuer::rotate/revoke,
 * RotateTokenCommand::rotateAllForUser e ScorecardBuilderService::buildFacts
 * já tratavam `mcp_tokens` como soft-deletable (`delete()`,
 * `whereNull('deleted_at')`, `withTrashed()`) mas a coluna nunca existiu —
 * "Unknown column 'deleted_at'" em QUALQUER run MySQL (inclusive prod).
 *
 * Tier 0 SEGREDO (ADR 0081): coluna ADITIVA nullable, NÃO-destrutiva. Preserva
 * o audit trail LGPD — rotate/revoke agora soft-deletam (a row sobrevive) em
 * vez de hard-delete. Pareado com o trait `SoftDeletes` em McpToken.
 *
 * REPO-WIDE: `mcp_tokens` é per-user (sem `business_id` — ADR 0053), exceção
 * documentada de multi-tenant (token vincula a user, não a business).
 */
class AddSoftDeletesToMcpTokensTable extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('mcp_tokens', 'deleted_at')) {
            Schema::table('mcp_tokens', function (Blueprint $table) {
                $table->softDeletes(); // deleted_at TIMESTAMP NULL
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('mcp_tokens', 'deleted_at')) {
            Schema::table('mcp_tokens', function (Blueprint $table) {
                $table->dropSoftDeletes(); // dropColumn('deleted_at')
            });
        }
    }
}
