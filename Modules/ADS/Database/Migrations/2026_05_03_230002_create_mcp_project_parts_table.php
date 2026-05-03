<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Project Parts = decomposição estratégica do projeto (Project Decomposer).
 * Cada part agrupa 1+ decisions executáveis e tem viability própria.
 */
class CreateMcpProjectPartsTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_project_parts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedSmallInteger('ordem')->default(1);

            $table->string('codigo', 50);                        // ex: PROJ-001-PART-A
            $table->string('nome', 200);
            $table->text('objetivo');
            $table->json('dependencias')->nullable();            // [parts.id, ...]
            $table->json('arquivos_estimados')->nullable();      // [path1, path2]

            $table->enum('status', ['pending', 'planning', 'in_progress', 'done', 'blocked', 'cancelled'])
                  ->default('pending');
            $table->unsignedTinyInteger('viability_score')->nullable();
            $table->unsignedTinyInteger('risco')->nullable();    // 0-100
            $table->unsignedSmallInteger('estimativa_horas')->nullable();
            $table->decimal('valor_estimado_brl', 10, 2)->nullable();

            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('mcp_projects')->onDelete('cascade');
            $table->index('status');
            $table->unique(['project_id', 'codigo'], 'uk_part_project_codigo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_project_parts');
    }
}
