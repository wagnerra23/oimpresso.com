<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs_links — N-para-N entre evidências e requisitos.
 *
 * Uma evidência pode apoiar múltiplas user stories. Um requisito pode
 * ser apoiado por múltiplas evidências.
 *
 * role:
 *   - origin        — evidência que ORIGINOU o requisito
 *   - supports      — evidência que CONFIRMA regra existente
 *   - contradicts   — evidência que CONTRADIZ (alerta de drift)
 *   - example       — screenshot ou exemplo ilustrativo
 */
class CreateDocsLinksTable extends Migration
{
    public function up(): void
    {
        Schema::create('docs_links', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('evidence_id')->index();
            $table->unsignedBigInteger('requirement_id')->index();
            $table->string('role', 16)->default('supports');         // origin, supports, contradicts, example
            $table->unsignedBigInteger('linked_by')->nullable();    // user
            $table->timestamp('linked_at')->nullable();
            $table->timestamps();

            $table->unique(['evidence_id', 'requirement_id', 'role'], 'docs_links_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docs_links');
    }
}
