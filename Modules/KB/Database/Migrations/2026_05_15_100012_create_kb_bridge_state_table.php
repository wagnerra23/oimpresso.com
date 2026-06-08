<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration kb_bridge_state — estado persistido do KbBridgeFromMcpJob.
 *
 * Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md §10
 *
 * 1 linha por business — `last_bridge_at` permite bridge incremental
 * (mcp_memory_documents.updated_at > last_bridge_at). Sem essa tabela,
 * cada run faria full sweep dos 352+ docs.
 *
 * Tabela utilitária extra (não está nas "11 tabelas novas" do contrato,
 * mas é necessária pra implementar o bridge incremental da §10).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('kb_bridge_state')) {
            return;
        }

        Schema::create('kb_bridge_state', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->timestamp('last_bridge_at')->nullable();
            $table->unsignedInteger('docs_processed_last_run')->default(0);
            $table->unsignedInteger('edges_derived_last_run')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique('business_id', 'uq_kb_bridge_state_business');

            $table->foreign('business_id', 'fk_kb_bridge_state_business')
                ->references('id')->on('business')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_bridge_state');
    }
};
