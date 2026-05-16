<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration kb_decision_trees — troubleshooter (grafo Q→Sim/Não→Fix).
 *
 * Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md §7
 *
 * `root_step_id` é populado em segundo INSERT após criar o primeiro step
 * (FK circular — registrar em transação no Controller).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('kb_decision_trees')) {
            return;
        }

        Schema::create('kb_decision_trees', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->string('slug', 120);
            $table->string('title', 180);
            $table->string('equip', 80)->nullable();
            $table->string('when_to_use', 500)->nullable()
                ->comment('descrição do sintoma');
            $table->unsignedSmallInteger('hue')->default(240);
            $table->string('status', 40)->default('published')
                ->comment('draft|published|archived');
            $table->unsignedBigInteger('root_step_id')->nullable()
                ->comment('primeiro passo (entry point) — populado após criação');
            $table->unsignedBigInteger('author_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'slug'], 'uq_kb_dt_biz_slug');
            $table->index(['business_id', 'status'], 'idx_kb_dt_biz_status');

            $table->foreign('business_id', 'fk_kb_dt_business')
                ->references('id')->on('business')->onDelete('cascade');

            $table->foreign('author_user_id', 'fk_kb_dt_author')
                ->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_decision_trees');
    }
};
