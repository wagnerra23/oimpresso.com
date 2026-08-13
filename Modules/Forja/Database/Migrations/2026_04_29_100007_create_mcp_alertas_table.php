<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MEM-MCP-1.a (ADR 0053) — Alertas configuráveis sobre uso MCP.
 *
 * Triggers automáticos disparados pelo job de agregação diária ou em
 * tempo real pelo middleware de audit.
 *
 * Kinds suportados:
 *  - cota_excedida: user passou da quota mensal/diária
 *  - tool_destrutiva: chamada a tool com is_destructive=true
 *  - ip_suspeito: IP fora de allowlist (futuro)
 *  - taxa_errors: >X% das calls com status=error em janela
 *  - cliente_externo: token de business_id externo invocou (futuro)
 */
class CreateMcpAlertasTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_alertas', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id')->nullable()
                ->comment('null = alerta global da plataforma');
            $table->unsignedInteger('business_id')->nullable();
            $table->enum('kind', [
                'cota_excedida',
                'tool_destrutiva',
                'ip_suspeito',
                'taxa_errors',
                'cliente_externo',
            ]);
            $table->decimal('threshold', 14, 4)->nullable()
                ->comment('Para cota_excedida: % da quota; para taxa_errors: %; etc');
            $table->enum('canal', ['in_app', 'email', 'whatsapp'])->default('in_app');
            $table->boolean('ativo')->default(true);
            $table->json('config_extra')->nullable()
                ->comment('Configuração específica do kind (ex: ip_allowlist)');
            $table->timestamps();

            $table->index(['kind', 'ativo'], 'mcp_alt_kind_ativo_idx');
            $table->index('user_id', 'mcp_alt_user_idx');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_alertas');
    }
}
