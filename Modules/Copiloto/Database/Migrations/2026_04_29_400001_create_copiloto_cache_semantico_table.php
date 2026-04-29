<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MEM-CACHE-1 (ADR 0037 Sprint 8) — Cache semântico de respostas LLM.
 *
 * Antes de chamar OpenAI: calcula embedding da query, busca cache_semantico
 * por cosine similarity ≥ 0.95 (mesmo tópico mesmo escopo). Se hit → retorna
 * resposta cacheada com hits++, sem chamar LLM. Se miss → chama LLM normal,
 * grava resposta com embedding+TTL.
 *
 * Estado-da-arte 2026: -68.8% tokens em produção (anthropic/openai blogs).
 *
 * Estratégia de chave:
 *   cache_key = SHA256(business_id + user_id + query_normalizada)
 *   query_normalizada = trim, lowercase, NFKD-fold accents, sort whitespace
 *
 * Por que não Redis? MySQL é fonte de verdade + queryable + auditável.
 * Cache hit em pequena escala (<10k entradas) é < 50ms via FULLTEXT.
 *
 * TTL default: 1h (curto p/ dados que mudam frequente — faturamento muda
 * ao longo do dia). Configurável por categoria de query no futuro.
 */
class CreateCopilotoCacheSemanticoTable extends Migration
{
    public function up(): void
    {
        Schema::create('copiloto_cache_semantico', function (Blueprint $t) {
            $t->bigIncrements('id');

            // Chave canônica
            $t->char('cache_key', 64)->unique()
                ->comment('SHA256(biz + user + query_normalizada)');

            // Scope (cross-tenant safety + privacy)
            $t->unsignedInteger('business_id')->nullable()->index();
            $t->unsignedInteger('user_id')->nullable()->index();

            // Query original
            $t->text('query_original')
                ->comment('Texto original do user (max 5000 chars)');
            $t->text('query_normalizada')
                ->comment('Normalizada pra comparação fuzzy (lowercase + sem acentos + sem espaços extras)');
            $t->binary('query_embedding')->nullable()
                ->comment('1536 floats × 4 bytes = 6KB (text-embedding-3-small)');

            // Resposta cacheada
            $t->mediumText('resposta')
                ->comment('Resposta completa do LLM (markdown)');
            $t->json('metadata')->nullable()
                ->comment('Modelo, tokens originais, contexto recall, etc.');

            // Stats de uso
            $t->unsignedInteger('hits')->default(0)
                ->comment('Quantas vezes essa entrada foi reutilizada (cache hit)');
            $t->timestamp('ultimo_hit_em')->nullable();

            // Custos
            $t->unsignedInteger('tokens_in')->nullable();
            $t->unsignedInteger('tokens_out')->nullable();
            $t->decimal('custo_brl_original', 10, 6)->nullable()
                ->comment('Custo da PRIMEIRA chamada (sem cache). Hits subsequentes economizam isso.');

            // TTL
            $t->timestamp('expira_em')->nullable()->index();

            $t->timestamps();

            $t->index(['business_id', 'user_id', 'expira_em'], 'cs_biz_user_exp_idx');
            $t->fullText('query_normalizada', 'cs_query_ft');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copiloto_cache_semantico');
    }
}
