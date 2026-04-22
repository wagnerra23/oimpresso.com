<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs_requirements — índice SQL dos user stories e regras que estão
 * nos arquivos markdown em memory/requisitos/.
 *
 * NÃO é fonte da verdade — os arquivos .md são. Esta tabela é cache +
 * rastreamento: permite queries ("US sem implementação", "regras sem
 * teste", etc).
 *
 * Repopulada por `docs:sync` (comando a implementar na Fase 3).
 */
class CreateDocsRequirementsTable extends Migration
{
    public function up(): void
    {
        Schema::create('docs_requirements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('business_id')->index();
            $table->string('module_target', 64)->index();               // Essentials, PontoWr2…
            $table->string('external_id', 32)->unique();                // US-ESSE-001, R-ESSE-007
            $table->string('kind', 16);                                 // user_story, rule
            $table->string('title', 255);
            $table->text('body')->nullable();                           // corpo do markdown (snapshot)
            $table->string('status', 24)->default('draft');             // draft, active, deprecated
            $table->string('implementado_em', 500)->nullable();         // resources/js/Pages/Essentials/Todo/Index.tsx
            $table->string('testado_em', 500)->nullable();              // Modules/Essentials/Tests/Feature/TodoTest.php
            $table->integer('dod_total')->default(0);
            $table->integer('dod_done')->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index(['module_target', 'kind']);
            $table->index(['status', 'module_target']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docs_requirements');
    }
}
