<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link + PIN da área da associada no app (ADR 0451 D6, onda 6 · US-ASSOC-005).
 *
 * Mesmo desenho do `fisio_portal_tokens` (US-FISIO-007a): o banco guarda SÓ hashes; o token e o
 * PIN em claro existem apenas na mensagem que a associação manda. Um link vivo por associada.
 * Revogar preenche `revogado_em`, nunca apaga (rastro de quem teve acesso e até quando).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('assoc_portal_tokens')) {
            return;
        }

        Schema::create('assoc_portal_tokens', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->index();
            $t->unsignedBigInteger('associada_id');
            $t->char('token_hash', 64)->comment('sha256 do token; o token em claro não é guardado');
            $t->string('pin_hash', 255);
            $t->unsignedTinyInteger('tentativas')->default(0);
            $t->dateTime('travado_ate')->nullable()->comment('trava depois de N PINs errados');
            $t->dateTime('expires_at');
            $t->dateTime('revogado_em')->nullable();
            $t->unsignedInteger('criado_por')->nullable();
            $t->timestamps();
            $t->unique('token_hash', 'assoc_portal_token_hash_unique');
            $t->index(['business_id', 'associada_id', 'revogado_em'], 'assoc_portal_biz_assda_rev_idx');
            $t->foreign('business_id')->references('id')->on('business');
            $t->foreign('associada_id')->references('id')->on('assoc_associadas');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assoc_portal_tokens');
    }
};
