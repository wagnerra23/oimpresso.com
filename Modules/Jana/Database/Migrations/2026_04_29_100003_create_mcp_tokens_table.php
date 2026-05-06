<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MEM-MCP-1.a (ADR 0053) — Tokens MCP (extensão sobre Sanctum).
 *
 * Sanctum nativo já tem `personal_access_tokens`. Esta tabela adiciona
 * campos específicos do MCP: cache de scopes resolvidos, user_agent
 * registrado, expiração customizável, identificação por dispositivo
 * (Wagner pode ter token "laptop" e token "desktop" separados).
 *
 * Token raw é hashed com sha256; nunca armazenado em claro.
 */
class CreateMcpTokensTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_tokens', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id');
            $table->string('name', 120)
                ->comment('Identificador human-readable: "Wagner laptop", "Felipe desktop"');
            $table->string('sha256_token', 64)->unique()
                ->comment('SHA256 do token raw — NUNCA armazena claro');
            $table->json('scopes_cache')->nullable()
                ->comment('Snapshot dos scopes na geração (cache pra evitar JOIN em cada chamada)');
            $table->string('user_agent', 200)->nullable();
            $table->ipAddress('last_used_ip')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()
                ->comment('null = não expira (revogar manual); setado = expira automático');
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedInteger('revoked_by')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at'], 'mcp_tk_user_idx');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_tokens');
    }
}
