<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0234 (Onda 1.1) — Registry de Automações: entidade canônica DB-primary.
 *
 * Espelha a anatomia do par skills (ADR 0076): DB é primary, filesystem é a
 * fonte (.claude/hooks/ + Kernel.php + .claude/*.json), AutomationRegistrySync
 * espelha, drift detection alerta em mcp_alertas_eventos (tipo=automation_drift).
 *
 * GLOBAL by-design — business_id NULL = infra de plataforma (ADR 0093 exceção,
 * igual mcp_skills / mcp_governance_rules). Hooks/crons/rotinas governam o repo
 * inteiro, não dados de tenant.
 *
 * Idempotente (Schema::hasTable guard) + down() — regra migrations oimpresso.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mcp_automations')) {
            return;
        }

        Schema::create('mcp_automations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('slug', 100)->unique()
                ->comment('Identificador único da automação (= basename do hook / comando do cron / slug do manifesto)');
            $table->unsignedBigInteger('business_id')->nullable()
                ->comment('NULL = global (registry de infra de plataforma, sem tenant — ADR 0093)');

            $table->enum('tipo', [
                'hook_sessionstart',
                'hook_pretooluse',
                'hook_posttooluse',
                'cron',
                'routine',
                'webhook',
            ])->comment('Classe da automação — gatilho determina o tipo');

            $table->string('gatilho', 255)
                ->comment('texto livre: matcher do hook (ex Edit|Write) OU expressao cron (ex 0 6 * * *) OU "SessionStart pos brief-fetch"');
            $table->text('descricao')->nullable();
            $table->string('arquivo', 300)
                ->comment('path relativo ao repo (ex .claude/hooks/pii-redactor.ps1)');
            $table->string('owner', 100)->nullable();
            $table->string('governed_by_adr', 100)->nullable()
                ->comment('slug do ADR que governa esta automacao (nullable)');

            $table->boolean('enabled')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->enum('last_status', ['ok', 'warn', 'fail', 'skip'])->nullable();
            $table->text('last_detail')->nullable();

            $table->timestamps();

            $table->index('tipo', 'idx_automations_tipo');
            $table->index('enabled', 'idx_automations_enabled');
            $table->index('last_status', 'idx_automations_last_status');
            $table->index('business_id', 'idx_automations_business');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_automations');
    }
};
