<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MEM-CC-1 (ADR 0054 a criar) — Sessions Claude Code do time, agregadas no servidor.
 *
 * Cada dev (Wagner, Felipe, Maíra, Luiz, Eliana) ingere via watcher local
 * `~/.claude/projects/*.jsonl` → POST /api/cc/ingest. 1 linha aqui por session
 * (UUID gerado pelo Claude Code). Idempotente via `session_uuid UNIQUE`.
 *
 * Permite: time saber o que cada um fez, busca cross-dev, dedup por SHA256
 * de tool_results pra economizar tokens em re-trabalho semelhante.
 */
class CreateMcpCcSessionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_cc_sessions', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('session_uuid', 36)->unique()
                ->comment('UUID da session do Claude Code (gerado pelo CC)');

            // Quem rodou
            $t->unsignedInteger('user_id')->index()
                ->comment('User do oimpresso que ingeriu — RBAC scope');
            $t->unsignedInteger('business_id')->nullable()->index();

            // Onde rodou
            $t->string('project_path', 500)
                ->comment('cwd: D:\\oimpresso.com, /home/x/proj, etc.');
            $t->string('git_branch', 150)->nullable();
            $t->string('cc_version', 20)->nullable()
                ->comment('Versão do Claude Code: 2.1.119, etc.');
            $t->string('entrypoint', 50)->nullable()
                ->comment('claude-desktop|claude-code-cli|claude-agent-sdk|...');

            // Métricas agregadas
            $t->timestamp('started_at')->nullable();
            $t->timestamp('ended_at')->nullable();
            $t->unsignedInteger('total_messages')->default(0);
            $t->unsignedBigInteger('total_tokens')->default(0);
            $t->decimal('total_cost_usd', 12, 6)->default(0);
            $t->decimal('total_cost_brl', 12, 4)->default(0);

            $t->enum('status', ['active', 'closed', 'archived'])->default('active');
            $t->json('metadata')->nullable()
                ->comment('Tags, notes, summary auto-gerado, etc.');
            $t->text('summary_auto')->nullable()
                ->comment('Sumário compacto LLM-gerado (~200 tokens) pra search rápido');

            $t->timestamps();
            $t->softDeletes();

            $t->index(['user_id', 'started_at'], 'cc_sess_user_started_idx');
            $t->index(['project_path', 'started_at'], 'cc_sess_proj_started_idx');
            $t->index(['business_id', 'started_at'], 'cc_sess_biz_started_idx');
            $t->fullText('summary_auto', 'cc_sess_summary_ft');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_cc_sessions');
    }
}
