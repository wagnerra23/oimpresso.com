<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MEM-MCP-1.a (ADR 0053) — Mapping user → scopes do MCP.
 *
 * Estende Spatie permissions com escopo opcional por business_id.
 * Permite ex: Eliana ter acesso a `copiloto.mcp.metrics.read` SOMENTE
 * para business_id=4 (ROTA LIVRE), não para outros tenants.
 */
class CreateMcpUserScopesTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_user_scopes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id');
            $table->unsignedBigInteger('scope_id');
            $table->unsignedInteger('business_id')->nullable()
                ->comment('null = todos os businesses; setado = limita a esse tenant');
            $table->unsignedInteger('granted_by')->nullable()
                ->comment('user_id que concedeu o acesso (audit)');
            $table->timestamp('granted_at')->useCurrent();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedInteger('revoked_by')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at'], 'mcp_us_user_idx');
            $table->index(['scope_id', 'business_id'], 'mcp_us_scope_biz_idx');
            $table->foreign('scope_id')->references('id')->on('mcp_scopes')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_user_scopes');
    }
}
