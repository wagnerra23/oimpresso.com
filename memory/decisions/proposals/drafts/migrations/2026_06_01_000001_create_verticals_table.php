<?php

/**
 * DRAFT — NÃO EXECUTAR DIRETO.
 *
 * Proposta: Modules/Insights — tabela `verticals` (52 verticais oimpresso).
 * Origem: memory/decisions/proposals/gap-schema-oimpresso-multi-cliente-multi-vertical.md
 *
 * Restrições Wagner 2026-05-09:
 *   Mudanças tenancy/multi-tenant exigem Pest local antes de PR.
 *   Felipe deve revisar + rodar Pest antes de commit.
 *
 * Ordem de execução: 1 de 4 (não tem FK pra business — pode rodar primeiro).
 *
 * Backwards compat:
 *   - Tabela nova, zero risco em migrations existentes.
 *   - Self-referencing parent_id permite hierarquia (vertical pai → sub-vertical).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('verticals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('slug', 50)->unique()->comment('Identificador estável (ex: comunicacao_visual)');
            $table->string('name', 100)->comment('Nome PT-BR');
            $table->string('name_plural', 100)->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('verticals')->nullOnDelete()
                ->comment('Hierarquia: vertical pai (ex: Saúde > Odontologia)');
            $table->json('cnae_codes')->nullable()
                ->comment('Lista CNAEs primários que mapeiam pra esta vertical (formato ["1813-0/01", ...])');
            $table->json('attributes_schema')->nullable()
                ->comment('JSONSchema dos atributos custom da vertical (ex: m² produzidos, # boxes)');
            $table->json('benchmark_metrics')->nullable()
                ->comment('Métricas relevantes pra benchmark (R$/m², R$/box, ticket médio, etc)');
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('parent_id');
            $table->index(['active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verticals');
    }
};
