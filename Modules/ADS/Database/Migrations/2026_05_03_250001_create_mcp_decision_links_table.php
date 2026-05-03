<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela pivot polimórfica — vincula ADRs (memory/decisions/* via slug)
 * a entidades do ADS (Projects, Skills, Decisions, Meta-skills).
 *
 * Permite auditoria reversa: "ADR 0011 está sendo usada em quais projects?"
 */
class CreateMcpDecisionLinksTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_decision_links', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('target_type', 30);   // project | skill | decision | metaskill
            $table->unsignedBigInteger('target_id');
            $table->string('adr_slug', 200);     // ex: 0011-jana-modulo-pattern
            $table->string('relation', 30)->default('referenced');
            //   referenced | implements | supersedes | conflicts_with | derived_from

            $table->string('created_by', 50)->default('system');
            $table->timestamps();

            $table->unique(['target_type', 'target_id', 'adr_slug', 'relation'], 'uk_decision_link');
            $table->index(['adr_slug', 'target_type'], 'idx_link_reverse');
            $table->index(['target_type', 'target_id'], 'idx_link_forward');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_decision_links');
    }
}
