<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MEM-MCP-1.a (ADR 0053) — Cache governado da memory/ do projeto.
 *
 * Decisão crítica: git é fonte de verdade; esta tabela é cache. Container
 * Proxmox NUNCA tem `memory/` no disco — só lê desta tabela via SSH tunnel.
 *
 * Vantagens vs filesystem (10 capacidades — ver ADR 0053 §Pilar 6):
 *   1. Audit por SELECT mesmo após RCE no container
 *   2. Permissão por linha (scope_required)
 *   3. Encryption at rest nativo MySQL
 *   4. Backup unificado com governança
 *   5. Versionamento queryable (mcp_memory_documents_history)
 *   6. PII redaction automática no sync
 *   7. Full-text search nativo (FULLTEXT)
 *   8. Vector search pronto (BLOB column)
 *   9. Soft-delete LGPD configurável
 *   10. Onboarding sem clone do repo
 *
 * Sync: job IndexarMemoryGitParaDb scaneia memory/, parseia frontmatter,
 * redacta PII (regex CPF/CNPJ), UPSERT por slug.
 */
class CreateMcpMemoryDocumentsTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_memory_documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('slug', 200)->unique()
                ->comment('Identificador único, ex: 0046-chat-agent-gap-contexto-rico');
            $table->enum('type', ['adr', 'session', 'reference', 'spec', 'handoff', 'current', 'tasks', 'other'])
                ->index();
            $table->string('module', 50)->nullable()
                ->comment('copiloto | financeiro | core | infra | null');

            $table->string('title', 250);
            $table->mediumText('content_md')
                ->comment('Conteúdo redactado (sem PII) em Markdown');

            $table->string('scope_required', 100)->nullable()
                ->comment('Se setado, exige Spatie permission. null = público pra autenticados');
            $table->boolean('admin_only')->default(false);

            $table->json('metadata')->nullable()
                ->comment('Frontmatter parseado: data, autor, status ADR, etc');

            $table->string('git_sha', 40)->nullable()
                ->comment('SHA do commit que gerou esta versão');
            $table->string('git_path', 300)
                ->comment('Caminho original no repo: memory/decisions/0046-...');

            $table->unsignedSmallInteger('pii_redactions_count')->default(0)
                ->comment('Quantos campos foram redactados pelo PII redactor');
            $table->binary('embedding')->nullable()
                ->comment('Vector embedding (futuro) — text-embedding-3-small 1536-dim');

            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('type', 'mcp_md_type_idx');
            $table->index('module', 'mcp_md_module_idx');
            $table->index('git_sha', 'mcp_md_sha_idx');
            $table->index(['scope_required', 'admin_only'], 'mcp_md_perms_idx');

            // Full-text search nativo MySQL — habilita decisions.search()
            $table->fullText(['title', 'content_md'], 'mcp_md_fulltext_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_memory_documents');
    }
}
