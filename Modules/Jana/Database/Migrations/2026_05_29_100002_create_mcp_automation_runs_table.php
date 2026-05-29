<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0234 (Onda 1.1) — Execuções de automação (audit append-only).
 *
 * Espelha mcp_skill_versions (append-only) e segue a regra de imutabilidade de
 * audit (ENFORCEMENT.md §L7): cada execução é um INSERT, nunca UPDATE/DELETE
 * em rows existentes. Separado do snapshot vivo em mcp_automations.last_* (mesma
 * separação histórico imutável vs estado corrente que ADR 0076 faz).
 *
 * Retenção via jana:retention-purge (já existe no Kernel) se o volume crescer.
 *
 * Idempotente (Schema::hasTable guard) + down().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mcp_automation_runs')) {
            return;
        }

        Schema::create('mcp_automation_runs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('automation_id');
            $table->timestamp('ran_at');
            $table->enum('status', ['ok', 'warn', 'fail', 'skip']);
            $table->text('detail')->nullable();
            $table->string('actor', 100)->nullable()
                ->comment('quem/o-que disparou: "scheduler", "claude-code:SessionStart", username...');

            $table->timestamps();

            $table->index(['automation_id', 'ran_at'], 'idx_runs_automation_ran');
            $table->index('status', 'idx_runs_status');

            $table->foreign('automation_id', 'fk_runs_automation')
                ->references('id')->on('mcp_automations')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_automation_runs');
    }
};
