<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration kb_paths — trilha de aprendizado (sequência ordenada de kb_nodes por persona).
 *
 * Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md §6
 *
 * NÃO inclui kb_path_user_progress (cloud sync de checkbox) — V1 fica em
 * localStorage `oimpresso.kb.paths`. Trade-off documentado em SCHEMA §15.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('kb_paths')) {
            return;
        }

        Schema::create('kb_paths', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->string('slug', 120);
            $table->string('title', 180);
            $table->string('audience', 180)->nullable()
                ->comment('Larissa primeiro mês, Wagner onboarding governança, etc.');
            $table->string('description', 500)->nullable();
            $table->unsignedSmallInteger('hue')->default(240);
            $table->string('status', 40)->default('published')
                ->comment('draft|published|archived');
            $table->unsignedBigInteger('author_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'slug'], 'uq_kb_paths_biz_slug');
            $table->index(['business_id', 'status'], 'idx_kb_paths_biz_status');

            $table->foreign('business_id', 'fk_kb_paths_business')
                ->references('id')->on('business')->onDelete('cascade');

            $table->foreign('author_user_id', 'fk_kb_paths_author')
                ->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_paths');
    }
};
