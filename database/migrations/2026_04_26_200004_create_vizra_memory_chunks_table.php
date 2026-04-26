<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * vizra_memory_chunks — memória vetorial em MySQL atual.
 *
 * Embedding como VARBINARY (768 dim Voyage-3-lite × 4 bytes float32 = 3072B).
 * Cosine similarity calculado em PHP (até ~10k chunks tranquilo).
 *
 * @see memory/requisitos/EvolutionAgent/adr/arq/0003-memoria-no-mysql-atual.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vizra_memory_chunks', function (Blueprint $table) {
            $table->id();
            $table->string('source_path', 512);
            $table->string('content_hash', 64)->index();
            $table->string('heading', 255)->nullable();
            $table->text('chunk_text');
            $table->binary('embedding')->nullable();
            $table->unsignedInteger('tokens')->default(0);
            $table->string('scope_module', 64)->nullable();
            $table->string('scope_type', 64)->nullable();
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();

            $table->index(['scope_module', 'scope_type'], 'idx_vizra_chunks_scope');
            $table->unique(['source_path', 'content_hash'], 'uq_vizra_chunks_source_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vizra_memory_chunks');
    }
};
