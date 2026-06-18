<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * error_groups — agrupamento de erros por dedupKey (Fase 2 · E-2 "Absorver").
 *
 * 1000 ocorrências do mesmo erro = 1 linha com contador, nunca 1000 pings.
 *
 * Tabela de PLATAFORMA/governança: **repo-wide, SEM business_id** no scope de
 * leitura — o `dedup_key` (hash classe+local+business) já carrega o business
 * afetado, e a leitura é cross-tenant (mesma natureza de `mcp_audit_log` e
 * `feature_flag_audits`). Decisão arquitetural autorizada pelo handoff de design
 * `erros-dedup` (#2938) — documentada no PR body.
 *
 * Idempotente (Schema::hasTable guard) + down() reversível.
 *
 * @see prototipo-ui/handoffs/erros-dedup.md
 * @see ADR 0093 (multi-tenant — exceção repo-wide documentada)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('error_groups')) {
            return;
        }

        Schema::create('error_groups', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('dedup_key', 64)->unique();        // hash(classe+local+business)
            $table->string('severity', 4)->index();           // S0..S3
            $table->string('audience', 16);                   // operador/construtor/ambos
            $table->string('owner', 60)->nullable();
            $table->unsignedBigInteger('count')->default(1);
            $table->string('status', 16)->default('open')->index(); // open/muted/resolved/archived
            $table->timestamp('first_seen')->nullable();
            $table->timestamp('last_seen')->nullable();
            $table->json('sample_payload')->nullable();        // amostra SEM PII (LGPD)
            $table->timestamps();

            $table->index(['status', 'last_seen'], 'eg_status_lastseen_idx');
            $table->index(['severity', 'last_seen'], 'eg_sev_lastseen_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_groups');
    }
};
