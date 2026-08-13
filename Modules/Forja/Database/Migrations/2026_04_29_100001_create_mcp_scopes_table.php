<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MEM-MCP-1.a (ADR 0053) — Catálogo de scopes do MCP server.
 *
 * Mapeia 1-pra-1 com Spatie permissions `copiloto.mcp.*`. Cada scope
 * descreve quais tools/resources o usuário pode invocar quando autenticado.
 */
class CreateMcpScopesTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_scopes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('slug', 100)->unique()
                ->comment('Spatie permission name, ex: copiloto.mcp.tasks.read');
            $table->string('nome', 150);
            $table->text('descricao')->nullable();
            $table->string('resources_pattern', 200)->nullable()
                ->comment('Glob/regex: oimpresso://memory/decisions/* — null=nenhum');
            $table->string('tools_pattern', 200)->nullable()
                ->comment('Glob/regex: tasks.*, decisions.* — null=nenhum');
            $table->boolean('is_destructive')->default(false)
                ->comment('Operação destrutiva exige approval flow');
            $table->boolean('business_required')->default(true)
                ->comment('Exige business_id no contexto da chamada');
            $table->boolean('admin_only')->default(false)
                ->comment('Só users com role superadmin podem invocar');
            $table->timestamps();

            $table->index('slug', 'mcp_scopes_slug_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_scopes');
    }
}
