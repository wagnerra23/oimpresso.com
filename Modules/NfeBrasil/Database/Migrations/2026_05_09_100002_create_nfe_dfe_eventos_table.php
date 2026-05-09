<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-NFE-050 · Tabela `nfe_dfe_eventos` — eventos SEFAZ aplicados ao DFe recebido.
 *
 * Tabela separada de `nfe_eventos` (eventos de NF-e EMITIDA) por SoC brutal —
 * domínios distintos (emissão vs manifestação). Append-only.
 *
 * Tipos canônicos NT 2014.002:
 *   210210 — Ciência da Operação
 *   210200 — Confirmação da Operação
 *   210220 — Desconhecimento da Operação
 *   210240 — Operação não Realizada
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nfe_dfe_eventos')) {
            return;
        }

        Schema::create('nfe_dfe_eventos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->foreignId('dfe_recebido_id')
                ->constrained('nfe_dfe_recebidos')
                ->cascadeOnDelete();
            $table->string('tipo', 6)
                ->comment('210210=ciência, 210200=confirmação, 210220=desconhecimento, 210240=não realizada');
            $table->text('justificativa')->nullable()
                ->comment('Obrigatória ≥15 chars pra 210220 e 210240 (NT 2014.002)');
            $table->enum('status', ['pendente', 'enviado', 'autorizado', 'rejeitado'])
                ->default('pendente')->index();
            $table->string('cstat_evento', 5)->nullable();
            $table->json('payload_json')->nullable();
            $table->unsignedSmallInteger('nseq_evento')->default(1);
            // append-only — sem updated_at
            $table->timestamp('created_at')->useCurrent();

            $table->index(['dfe_recebido_id', 'tipo'], 'nfe_dfe_eventos_dfe_tipo_idx');
            $table->unique(
                ['business_id', 'dfe_recebido_id', 'tipo', 'nseq_evento'],
                'nfe_dfe_eventos_uq'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfe_dfe_eventos');
    }
};
