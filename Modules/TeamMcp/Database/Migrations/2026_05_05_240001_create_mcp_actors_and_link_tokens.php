<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P0.4 do audit cascata Constitution v1.1.0 — Artigo 6 (Identity Mesh):
 * "todo actor (humano ou IA) tem manifest declarado".
 *
 * Cria tabela mcp_actors + ALTER mcp_tokens (adiciona actor_id) + ALTER users
 * (adiciona mcp_actor_id pra binding com sistema legacy).
 *
 * Supersede ADR 0077 — em vez de adicionar users.mcp_handle, criamos
 * actor_id explícito com manifest completo.
 *
 * Referências:
 * - ADR 0079 — Constituição Artigo 6
 * - ADR 0080 — Audit cascata findings
 * - ADR 0081 — Identity Mesh (este artefato)
 * - memory/governance/IDENTITY-MESH.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_actors', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('slug', 60)->unique();
            $table->enum('type', ['human', 'ai_agent', 'service']);
            $table->enum('trust_level', ['L0', 'L1', 'L2', 'L3', 'L4']);
            $table->unsignedBigInteger('parent_actor_id')->nullable()->index();

            // Capabilities como JSON arrays
            $table->json('modules_write')->comment('["Jana","KB"] ou ["*"]');
            $table->json('modules_read')->comment('["*"]');
            $table->json('modules_blocked')->comment('["Connector","Superadmin"]');
            $table->json('skills_required')->comment('["oimpresso-stack","multi-tenant-patterns"]');
            $table->json('actions_blocked')->comment('["drop_table","schema_destructive"]');

            $table->boolean('audit_required')->default(true);

            // Linking ao sistema legacy
            $table->unsignedInteger('user_id')->nullable()->index();
            $table->string('display_name', 120);

            // Audit trail
            $table->unsignedBigInteger('created_by_actor_id')->nullable();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->unsignedBigInteger('revoked_by_actor_id')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('trust_level');
        });

        Schema::table('mcp_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('actor_id')->nullable()->after('user_id')->index();
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'mcp_actor_id')) {
                $table->unsignedBigInteger('mcp_actor_id')->nullable()->after('username');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'mcp_actor_id')) {
                $table->dropColumn('mcp_actor_id');
            }
        });

        Schema::table('mcp_tokens', function (Blueprint $table) {
            $table->dropIndex(['actor_id']);
            $table->dropColumn('actor_id');
        });

        Schema::dropIfExists('mcp_actors');
    }
};
