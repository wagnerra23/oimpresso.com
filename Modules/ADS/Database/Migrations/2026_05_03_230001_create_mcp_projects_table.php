<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Project = unidade estratégica que agrupa decisões + ADRs + decomposição.
 * Resolve "ADR solto sem contexto" descrito no modelo Wagner.
 */
class CreateMcpProjectsTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_projects', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->string('codigo', 30)->unique();              // ex: PROJ-2026-001
            $table->string('nome', 200);
            $table->text('objetivo_macro');
            $table->json('metricas_sucesso');                    // [{nome, alvo, atual, deadline}]
            $table->json('constraints')->nullable();             // {tempo_dias, custo_max_brl, stack, etc}

            $table->enum('status', ['draft', 'active', 'paused', 'completed', 'killed'])
                  ->default('draft');
            $table->enum('decision', ['pending', 'proceed', 'pivot', 'kill'])
                  ->default('pending');

            // Viability score (calculado pelo ViabilityScoreService)
            $table->unsignedTinyInteger('viability_score')->nullable();   // 0-100
            $table->json('viability_factors')->nullable();                 // breakdown 5 fatores

            // ROI estimativa
            $table->decimal('custo_estimado_brl', 12, 2)->nullable();
            $table->decimal('valor_estimado_brl', 12, 2)->nullable();
            $table->unsignedSmallInteger('prazo_estimado_dias')->nullable();

            $table->string('owner', 50)->default('wagner');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->index('status');
            $table->index('decision');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_projects');
    }
}
