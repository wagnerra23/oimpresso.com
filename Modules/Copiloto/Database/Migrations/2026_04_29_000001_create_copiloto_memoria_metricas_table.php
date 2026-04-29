<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MEM-MET-1 (ADR 0050) — Tabela de métricas diárias de memória do Copiloto.
 *
 * 1 linha por dia por business_id (NULL = plataforma agregada). Persiste
 * as 8 métricas obrigatórias + contadores acessórios + payload JSON pra
 * rastreio (queries que falharam, perguntas do gabarito, etc).
 *
 * Índice único (apurado_em, business_id) garante idempotência da apuração
 * diária — re-rodar `copiloto:metrics:apurar --date=Y-m-d` faz upsert.
 *
 * Ver ADRs 0049 (camadas + gate Recall@3) e 0050 (régua das métricas).
 */
class CreateCopilotoMemoriaMetricasTable extends Migration
{
    public function up(): void
    {
        Schema::create('copiloto_memoria_metricas', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('apurado_em')->comment('Dia da apuração (YYYY-MM-DD)');
            $table->unsignedInteger('business_id')
                ->nullable()
                ->comment('NULL = plataforma agregada (todos os tenants)');

            // 8 métricas obrigatórias (ADR 0050)
            $table->decimal('recall_at_3', 4, 3)->nullable()
                ->comment('Meta > 0.80 — % das vezes que a memória correta apareceu nos top 3');
            $table->decimal('precision_at_3', 4, 3)->nullable()
                ->comment('Meta > 0.60 — % dos top 3 que eram realmente relevantes');
            $table->decimal('mrr', 4, 3)->nullable()
                ->comment('Meta > 0.70 — Mean Reciprocal Rank');
            $table->unsignedInteger('latencia_p95_ms')->nullable()
                ->comment('Meta < 2000 ms — ciclo recall + LLM + resposta');
            $table->unsignedInteger('tokens_medio_interacao')->nullable()
                ->comment('Meta < 3000 tokens/msg — custo operacional');
            $table->decimal('memory_bloat_ratio', 4, 3)->nullable()
                ->comment('Meta > 0.60 — memórias úteis (com hit em 30d) / total');
            $table->decimal('taxa_contradicoes_pct', 5, 2)->nullable()
                ->comment('Meta < 2.00 % — fatos contraditórios sem valid_until');
            $table->unsignedInteger('cross_tenant_violations')->default(0)
                ->comment('Meta = 0 — recall que retornou business_id alheio');

            // Métricas RAGAS-aligned (ADR 0051) — colunas reconhecidas nativamente
            // por RAGAS / DeepEval / Langfuse. Apuradas via golden set.
            $table->decimal('faithfulness', 4, 3)->nullable()
                ->comment('RAGAS — resposta vs contexto (sem alucinação); meta > 0.85');
            $table->decimal('answer_relevancy', 4, 3)->nullable()
                ->comment('RAGAS — resposta vs pergunta (relevância semântica); meta > 0.80');
            $table->decimal('context_precision', 4, 3)->nullable()
                ->comment('RAGAS — chunks recuperados ranqueados por relevância; meta > 0.70');

            // Contadores de contexto
            $table->unsignedInteger('total_interacoes_dia')->default(0)
                ->comment('Mensagens role=user no dia');
            $table->unsignedInteger('total_memorias_ativas')->default(0)
                ->comment('CopilotoMemoriaFato ativos no fim do dia');

            // Payload de rastreio (perguntas falhas, shape do eval, etc)
            $table->json('detalhes')->nullable();

            $table->timestamps();

            // Idempotência da apuração diária + lookup rápido por dia
            $table->unique(['apurado_em', 'business_id'], 'mem_metr_ux');
            $table->index('apurado_em', 'mem_metr_apurado_em_idx');
            $table->index('business_id', 'mem_metr_biz_idx');

            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copiloto_memoria_metricas');
    }
}
