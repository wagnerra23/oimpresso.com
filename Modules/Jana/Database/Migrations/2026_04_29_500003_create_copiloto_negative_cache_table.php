<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MEM-S10-3 — Negative cache: queries que retornaram zero hits.
 *
 * Antes: cada vez que user pergunta sobre coisa que não está no DB,
 * MeilisearchDriver faz round-trip 50-200ms pra retornar nada.
 *
 * Depois: marca cache_key + biz + user → "sem hits". Próxima query igual
 * salta direto pra null sem chamar Meilisearch (~5ms vs 200ms).
 *
 * TTL curto (15min default) — dado pode entrar no DB a qualquer momento.
 *
 * Trade-off:
 *   + Economia em queries OFF-TOPIC (ex: "qual previsão tempo amanhã?")
 *   + Economia em ataques exploratórios (red-team, scans)
 *   - Memory overhead pequeno (1 row por query unique → ~100 bytes)
 *   - Stale risk: dado novo entra no DB mas cache ainda diz vazio
 *   - Mitigação: TTL curto + invalidação por business em mudança
 */
class CreateCopilotoNegativeCacheTable extends Migration
{
    public function up(): void
    {
        Schema::create('copiloto_negative_cache', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->char('cache_key', 64)->unique()
                ->comment('SHA256(biz + user + query_normalizada) — mesma chave do positive cache');
            $t->unsignedInteger('business_id')->nullable();
            $t->unsignedInteger('user_id')->nullable();
            $t->text('query_normalizada');

            $t->unsignedInteger('hits_negativos')->default(0)
                ->comment('Quantas vezes evitamos round-trip Meilisearch graças a essa entrada');
            $t->timestamp('expira_em')->index();
            $t->timestamps();

            $t->index(['business_id', 'user_id', 'expira_em'], 'cnc_biz_user_exp_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copiloto_negative_cache');
    }
}
