<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration kb_comments — comments inline ancorados em block_idx.
 *
 * Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md §9
 *
 * `block_idx` = índice 0-based no array body_blocks do node.
 * Comment não é versionado — historico via soft-delete.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('kb_comments')) {
            return;
        }

        Schema::create('kb_comments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('node_id');
            $table->unsignedSmallInteger('block_idx')
                ->comment('index do bloco em body_blocks (0-based)');
            $table->text('text');
            // refs users.id (int unsigned, UltimatePOS legacy)
            $table->unsignedInteger('author_user_id');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'node_id', 'block_idx'], 'idx_kb_comments_biz_node_block');
            $table->index('author_user_id', 'idx_kb_comments_author');
            $table->index('deleted_at', 'idx_kb_comments_deleted');

            $table->foreign('business_id', 'fk_kb_comments_business')
                ->references('id')->on('business')->onDelete('cascade');

            $table->foreign('node_id', 'fk_kb_comments_node')
                ->references('id')->on('kb_nodes')->onDelete('cascade');

            $table->foreign('author_user_id', 'fk_kb_comments_author')
                ->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_comments');
    }
};
