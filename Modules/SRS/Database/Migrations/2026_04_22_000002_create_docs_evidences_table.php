<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs_evidences — fragmentos destilados de docs_sources.
 *
 * Uma source pode gerar N evidences. Exemplo: um chat log vira várias
 * evidences, cada uma com um ponto (bug, regra, fluxo mencionado).
 *
 * workflow: pending → triaged → applied (vira requirement) ou rejected.
 */
class CreateDocsEvidencesTable extends Migration
{
    public function up(): void
    {
        Schema::create('docs_evidences', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('source_id')->index();            // FK docs_sources
            $table->string('module_target', 64)->nullable()->index();   // pode refinar da source
            $table->string('kind', 24);                                  // bug, rule, flow, quote, screenshot, decision
            $table->string('status', 16)->default('pending')->index();  // pending, triaged, applied, rejected, duplicate
            $table->text('content');                                     // texto da evidência
            $table->decimal('ai_confidence', 3, 2)->nullable();          // 0.00..1.00 (se classificada por IA)
            $table->boolean('extracted_by_ai')->default(false);
            $table->string('suggested_story_id', 32)->nullable();       // ex: US-ESSE-003 (sugestão)
            $table->string('suggested_rule_id', 32)->nullable();        // ex: R-ESSE-007
            $table->unsignedBigInteger('triaged_by')->nullable();       // users.id do humano que aprovou
            $table->timestamp('triaged_at')->nullable();
            $table->text('notes')->nullable();                           // notas do triager
            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->index(['module_target', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docs_evidences');
    }
}
