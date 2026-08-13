<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MEM-MCP-1.a (ADR 0053) — Audit log IMUTÁVEL de toda chamada MCP.
 *
 * LGPD compliance: responder "quem acessou X em Y data" em <15 dias.
 * Cada chamada de tool/resource gera 1 linha. Retenção mínima 1 ano
 * (configurável via env). Sem UPDATE jamais — só INSERT (append-only).
 *
 * Métricas alimentam mcp_usage_diaria via job agregação 23:55.
 */
class CreateMcpAuditLogTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_audit_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('request_id')->unique()
                ->comment('UUID gerado por chamada — correlaciona com Claude Code session');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('business_id')->nullable();
            $table->timestamp('ts')->useCurrent()->index();

            $table->enum('endpoint', [
                'tools/list', 'tools/call',
                'resources/list', 'resources/read',
                'prompts/list', 'prompts/get',
                'initialize',
            ]);
            $table->string('tool_or_resource', 200)->nullable()
                ->comment('Nome da tool ou URI do resource invocado');
            $table->string('scope_required', 100)->nullable();

            $table->enum('status', ['ok', 'denied', 'error', 'quota_exceeded']);
            $table->string('error_code', 50)->nullable();
            $table->text('error_message')->nullable();

            // Métricas de custo (alimentam mcp_usage_diaria)
            $table->unsignedInteger('tokens_in')->nullable();
            $table->unsignedInteger('tokens_out')->nullable();
            $table->unsignedInteger('cache_read')->nullable();
            $table->unsignedInteger('cache_write')->nullable();
            $table->decimal('custo_brl', 10, 6)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            // Identificação
            $table->ipAddress('ip')->nullable();
            $table->string('user_agent', 200)->nullable();
            $table->string('claude_code_session', 36)->nullable()
                ->comment('SessionId do Claude Code (correlation com JSONLs locais)');
            $table->unsignedBigInteger('mcp_token_id')->nullable();

            $table->json('payload_summary')->nullable()
                ->comment('Resumo redactado dos args (sem PII)');

            $table->timestamp('created_at')->useCurrent();
            // Sem updated_at: tabela é IMUTÁVEL (append-only)

            $table->index(['user_id', 'ts'], 'mcp_al_user_ts_idx');
            $table->index(['business_id', 'ts'], 'mcp_al_biz_ts_idx');
            $table->index(['tool_or_resource', 'ts'], 'mcp_al_tool_ts_idx');
            $table->index(['status', 'ts'], 'mcp_al_status_ts_idx');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('mcp_token_id')->references('id')->on('mcp_tokens')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_audit_log');
    }
}
