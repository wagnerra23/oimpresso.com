<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0076 (Fase 1) — labels móveis Langfuse-style.
 *
 * Labels production/staging/dev apontam pra version_id. "Deploy" = mover
 * label. Rollback = mover de volta. previous_version_id mantém audit
 * de movimentações.
 *
 * Unique(skill_id, label): cada skill tem no máximo 1 production, 1 staging, 1 dev.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_skill_labels', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('skill_id');
            $table->enum('label', ['production', 'staging', 'dev']);
            $table->unsignedBigInteger('version_id');

            $table->unsignedBigInteger('moved_by')->nullable()
                ->comment('NULL na criação inicial via seeder/import');
            $table->timestamp('moved_at')->useCurrent();
            $table->unsignedBigInteger('previous_version_id')->nullable()
                ->comment('Versão anterior antes de mover label (audit rollback)');
            $table->text('reason')->nullable();

            $table->unique(['skill_id', 'label'], 'uk_skill_label');
            $table->index('version_id', 'idx_labels_version');

            $table->foreign('skill_id')
                ->references('id')->on('mcp_skills')
                ->cascadeOnDelete();
            $table->foreign('version_id')
                ->references('id')->on('mcp_skill_versions');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_skill_labels');
    }
};
