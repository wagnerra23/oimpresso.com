<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MEM-MCP-1.a (ADR 0053) — Agregação diária do uso MCP por usuário.
 *
 * Materializa view sobre mcp_audit_log pra dashboards rápidos
 * (`/copiloto/admin/custos` aba "Equipe"). 1 linha/user/dia/business.
 *
 * Comando `mcp:agregacao-diaria` roda 23:55 fechando o dia, similar a
 * MEM-MET-3 (`copiloto:metrics:apurar`).
 */
class CreateMcpUsageDiariaTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_usage_diaria', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('dia');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('business_id')->nullable()
                ->comment('null = agregado plataforma');

            $table->unsignedInteger('total_calls')->default(0);
            $table->unsignedInteger('calls_ok')->default(0);
            $table->unsignedInteger('calls_denied')->default(0);
            $table->unsignedInteger('calls_quota_exceeded')->default(0);
            $table->unsignedInteger('calls_error')->default(0);

            $table->unsignedBigInteger('total_tokens_in')->default(0);
            $table->unsignedBigInteger('total_tokens_out')->default(0);
            $table->unsignedBigInteger('total_cache_read')->default(0);
            $table->unsignedBigInteger('total_cache_write')->default(0);
            $table->decimal('custo_brl', 14, 4)->default(0);

            $table->json('top_tools')->nullable()
                ->comment('[{"tool":"decisions.fetch","calls":42}, ...] top 5');
            $table->unsignedInteger('alertas_disparados')->default(0);
            $table->boolean('excedeu_quota')->default(false);

            $table->timestamps();

            $table->unique(['dia', 'user_id', 'business_id'], 'mcp_ud_dia_user_biz_ux');
            $table->index('dia', 'mcp_ud_dia_idx');
            $table->index(['user_id', 'dia'], 'mcp_ud_user_dia_idx');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_usage_diaria');
    }
}
