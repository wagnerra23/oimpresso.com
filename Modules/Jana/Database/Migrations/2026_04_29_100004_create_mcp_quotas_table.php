<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MEM-MCP-1.a (ADR 0053) — Quotas configuráveis por usuário e período.
 *
 * Limita uso de IA por dev. Quando `current_usage >= limit`, MCP nega
 * chamadas com 429 Too Many Requests. Reset automático em `reset_at`.
 *
 * Suporta 2 unidades:
 *  - tokens: limite em tokens consumidos (in+out+cache)
 *  - brl: limite em R$ gastos (calculado via pricing config)
 */
class CreateMcpQuotasTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_quotas', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id');
            $table->enum('period', ['daily', 'weekly', 'monthly'])->default('monthly');
            $table->enum('kind', ['tokens', 'brl', 'calls'])->default('brl');
            $table->decimal('limit', 14, 4)
                ->comment('Limite no período (em tokens, BRL ou nº de calls)');
            $table->decimal('current_usage', 14, 4)->default(0);
            $table->timestamp('reset_at')
                ->comment('Próximo reset automático (calculado por period)');
            $table->boolean('block_on_exceed')->default(true)
                ->comment('true = retorna 429; false = só alerta, deixa passar');
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'period', 'kind'], 'mcp_qt_user_period_kind_ux');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_quotas');
    }
}
