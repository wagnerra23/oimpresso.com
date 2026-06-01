<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration kb_charter_suggestions — modo sugestão supervisionada (ADR 0243, F1).
 *
 * Charters são lidos do filesystem (*.charter.md) — read-only, núcleo no git.
 * A EVOLUÇÃO acontece por sugestão: o membro propõe (sem editar), o owner
 * aprova/rejeita com comentário. A sugestão ancora pelo `charter_path` (git_path
 * do .charter.md), não por FK a kb_nodes (charters não são nós editáveis).
 *
 * Workflow status: proposed → under_review → accepted | rejected (→ merged em F3
 * quando virar PR no .charter.md). Tier 0: business_id scope (ADR 0093).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('kb_charter_suggestions')) {
            return;
        }

        Schema::create('kb_charter_suggestions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');

            $table->string('charter_path', 255)
                ->comment('git_path do *.charter.md (âncora — resources/js/Pages/.../X.charter.md)');
            $table->string('anchor', 255)->nullable()
                ->comment('seção/trecho do charter a que a sugestão se refere (texto livre)');
            $table->string('kind', 20)->default('suggestion')
                ->comment('suggestion|question|erratum|comment');
            $table->text('text');
            $table->string('status', 20)->default('proposed')
                ->comment('proposed|under_review|accepted|rejected|merged');

            $table->unsignedInteger('author_user_id');
            $table->unsignedInteger('resolved_by_user_id')->nullable();
            $table->string('resolution_note', 500)->nullable()
                ->comment('comentário obrigatório no approve/reject (Document360-style)');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'charter_path', 'status'], 'idx_kbcs_biz_path_status');
            $table->index('author_user_id', 'idx_kbcs_author');
            $table->index('deleted_at', 'idx_kbcs_deleted');

            $table->foreign('business_id', 'fk_kbcs_business')
                ->references('id')->on('business')->onDelete('cascade');
            $table->foreign('author_user_id', 'fk_kbcs_author')
                ->references('id')->on('users')->onDelete('cascade');
            $table->foreign('resolved_by_user_id', 'fk_kbcs_resolver')
                ->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_charter_suggestions');
    }
};
