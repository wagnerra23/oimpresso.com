<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MEM-EVAL-1 (ADR 0049 + ADR 0050) — Gabarito de perguntas pro eval automatizado.
 *
 * Tabela `copiloto_memoria_gabarito`:
 *   - 50 perguntas Larissa-style (extraídas das conversas reais em prod)
 *   - cobertura: 5 categorias LongMemEval (info-extraction, multi-session,
 *     temporal, knowledge-update, abstention)
 *   - cada pergunta tem `memoria_esperada_keys` (JSON array de keys/snippets
 *     que DEVERIAM aparecer no recall) e `resposta_esperada_pattern` (regex
 *     ou substring que valida a resposta do agente)
 *
 * Comando `php artisan copiloto:metrics:apurar` lê desta tabela pra calcular
 * Recall@3, Precision@3, MRR, Faithfulness (via RAGAS LLM-judge).
 *
 * Maintenance: Wagner adiciona/edita perguntas conforme domínio evolui (novos
 * meses de faturamento, novos clientes, novos cenários).
 */
class CreateCopilotoMemoriaGabaritoTable extends Migration
{
    public function up(): void
    {
        Schema::create('copiloto_memoria_gabarito', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id')->nullable()
                ->comment('Business da pergunta (null = pergunta universal). FK soft.');
            $table->string('categoria', 50)
                ->comment('LongMemEval: info-extraction|multi-session|temporal|knowledge-update|abstention');
            $table->string('subcategoria', 50)->nullable()
                ->comment('Domínio: faturamento|clientes|metas|despesas|capability|cross-tenant|lgpd');
            $table->text('pergunta')
                ->comment('Pergunta no estilo do user real (Larissa, Wagner, etc.)');
            $table->json('memoria_esperada_keys')
                ->comment('Array de strings/snippets que DEVERIAM aparecer no recall. Match por contains.');
            $table->text('resposta_esperada_pattern')->nullable()
                ->comment('Regex ou substring que valida resposta do agente (NULL = só checa recall)');
            $table->json('contexto_setup')->nullable()
                ->comment('Fatos/dados que precisam estar no DB pra pergunta fazer sentido');
            $table->unsignedTinyInteger('dificuldade')->default(2)
                ->comment('1=trivial, 2=média, 3=difícil (multi-hop ou temporal)');
            $table->boolean('ativo')->default(true);
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'ativo'], 'cmg_biz_ativo_idx');
            $table->index('categoria', 'cmg_categoria_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copiloto_memoria_gabarito');
    }
}
