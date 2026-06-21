<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PR-1 Loop de Handoff Zero-Paste (Fase 0 · ADR 0283) — fonte da verdade dos
 * handoffs de design Cowork→Code.
 *
 * 1 linha por (slug, version). `handoff:ingest` valida HMAC e cria 'pending';
 * revisão de um slug já aplicado vira NOVA version 'pending' + a anterior
 * 'superseded' (append-only {@see ADR 0130}/0003 — NUNCA delete).
 *
 * Tier 0 ({@see ADR 0093}): SEM business_id — handoff de design é artefato do
 * REPO (cross-tenant), não dado de tenant. Espelha mcp_ingest_heartbeat /
 * mcp_cc_sessions. NUNCA adicionar global scope de business aqui.
 *
 * MySQL (DB_CONNECTION=mysql): a spec Cowork trazia DDL PostgreSQL
 * (BIGSERIAL/JSONB/TIMESTAMPTZ/índice parcial `WHERE status='pending'`). Aqui o
 * main vence: bigIncrements + json + timestamp; MySQL 8 não tem índice parcial
 * → índice simples em `status`.
 *
 * Idempotente (hasTable) + reversível (down dropIfExists).
 *
 * @see Modules\TeamMcp\Console\Commands\HandoffIngestCommand (writer)
 * @see memory/decisions/0283-handoff-loop-zero-paste.md
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cowork_handoffs')) {
            return;
        }

        Schema::create('cowork_handoffs', function (Blueprint $t) {
            $t->bigIncrements('id');

            $t->string('slug', 120)
                ->comment('Identificador do handoff (ex: caixa-mobile-flutuante)');
            $t->unsignedInteger('version')->default(1)
                ->comment('Revisão = nova versão (append-only ADR 0130/0003)');
            $t->string('tela', 160)
                ->comment('Tela alvo (ex: Atendimento/CaixaUnificada)');
            $t->string('status', 16)->default('pending')
                ->comment('pending|applied|rejected|stale|superseded');
            $t->string('audited_against', 40)->nullable()
                ->comment('SHA do main lido por [CC] na auditoria (R1 ADR 0283)');
            $t->longText('body_md')
                ->comment('Corpo do handoff — DESIGN (dado), não comando');
            $t->json('files_json')
                ->comment('Arquivos que o handoff autoriza tocar (escopo do PR)');
            $t->char('source_hash', 64)
                ->comment('sha256(body) — dedup de re-ingest sem mudança');
            $t->char('sig', 64)
                ->comment('HMAC-SHA256(body, HANDOFF_SECRET) — proveniência (A1)');
            $t->string('created_by', 40)->default('CC');
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('applied_at')->nullable()
                ->comment('Quando o ack fechou o handoff (PR-2)');
            $t->string('applied_by', 60)->nullable();
            $t->text('pr_url')->nullable();
            $t->json('gate_status')->nullable()
                ->comment('{conformance,critique_score,a11y} gravado no ack (A3)');

            $t->unique(['slug', 'version'], 'cowork_handoffs_slug_version_uq');
            $t->index('status', 'cowork_handoffs_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cowork_handoffs');
    }
};
