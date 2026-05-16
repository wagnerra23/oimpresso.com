<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration kb_favorites — bookmark por user.
 *
 * Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md §9
 *
 * UNIQUE (user_id, node_id) impede duplicação. Toggle no controller:
 * primeiro tenta firstOrCreate, se já existe → delete (toggle).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('kb_favorites')) {
            return;
        }

        Schema::create('kb_favorites', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('node_id');
            $table->timestamp('created_at')->nullable();

            // Sem updated_at — favorito é boolean.

            $table->unique(['user_id', 'node_id'], 'uq_kb_favorites_user_node');
            $table->index(['business_id', 'user_id'], 'idx_kb_favorites_biz_user');

            $table->foreign('business_id', 'fk_kb_favorites_business')
                ->references('id')->on('business')->onDelete('cascade');

            $table->foreign('user_id', 'fk_kb_favorites_user')
                ->references('id')->on('users')->onDelete('cascade');

            $table->foreign('node_id', 'fk_kb_favorites_node')
                ->references('id')->on('kb_nodes')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_favorites');
    }
};
