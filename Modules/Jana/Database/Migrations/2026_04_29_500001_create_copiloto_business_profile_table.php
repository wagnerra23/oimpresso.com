<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MEM-S8-3 — Profile destilado por business (job diário).
 *
 * Substitui o ContextoNegocio crus (calculado dinamicamente) por uma
 * narrativa compacta refresada 1×/dia. -30% tokens no system prompt.
 */
class CreateCopilotoBusinessProfileTable extends Migration
{
    public function up(): void
    {
        Schema::create('copiloto_business_profile', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedInteger('business_id')->unique()
                ->comment('1 profile por business — UNIQUE pra updateOrInsert');

            $t->text('profile_text')
                ->comment('Narrativa compacta destilada pelo LLM (~200 tokens)');
            $t->unsignedInteger('tokens_estimated')->default(0);
            $t->unsignedInteger('raw_context_tokens')->default(0)
                ->comment('Tokens dos dados crus que originaram (pra calcular compression)');

            $t->timestamp('gerado_em')->nullable()
                ->comment('Última vez que o LLM destilou — usar pra TTL');

            $t->timestamps();

            $t->index('gerado_em', 'cbp_gerado_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copiloto_business_profile');
    }
}
