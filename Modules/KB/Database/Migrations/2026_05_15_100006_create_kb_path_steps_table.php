<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration kb_path_steps — passos ordenados de uma trilha.
 *
 * Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md §6
 *
 * `position` é 1-based. UNIQUE (path_id, position) garante ordem sem gaps
 * lógicos (reordenar exige transação manual).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('kb_path_steps')) {
            return;
        }

        Schema::create('kb_path_steps', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('path_id');
            $table->unsignedBigInteger('node_id');
            $table->unsignedSmallInteger('position')->comment('1-based');
            $table->string('step_type', 40)->default('leitura')
                ->comment('leitura|pratica|decisao');
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->unique(['path_id', 'position'], 'uq_kb_path_steps_pos');
            $table->index(['business_id', 'path_id'], 'idx_kb_path_steps_biz_path');
            $table->index('node_id', 'idx_kb_path_steps_node');

            $table->foreign('business_id', 'fk_kb_path_steps_business')
                ->references('id')->on('business')->onDelete('cascade');

            $table->foreign('path_id', 'fk_kb_path_steps_path')
                ->references('id')->on('kb_paths')->onDelete('cascade');

            $table->foreign('node_id', 'fk_kb_path_steps_node')
                ->references('id')->on('kb_nodes')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_path_steps');
    }
};
