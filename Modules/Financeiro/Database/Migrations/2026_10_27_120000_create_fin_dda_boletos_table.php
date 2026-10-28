<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DDA — boletos emitidos contra o CNPJ da empresa (US-FIN-078).
 *
 * Cada linha é uma SUGESTÃO de conta a pagar: só vira `fin_titulos` quando um usuário aceita
 * (`titulo_id` preenchido, status `aceito`). O UNIQUE (business_id, codigo_barras) é a
 * idempotência: a fonte pode entregar o mesmo boleto várias vezes sem duplicar a sugestão.
 * `contact_id` é o fornecedor casado pelo CNPJ/CPF do beneficiário, nulo quando não há cadastro.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fin_dda_boletos')) {
            return;
        }

        Schema::create('fin_dda_boletos', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->index();
            $t->string('fonte', 30);
            $t->string('codigo_barras', 48);
            $t->string('beneficiario_documento', 14);
            $t->string('beneficiario_nome', 191)->nullable();
            $t->decimal('valor', 22, 4);
            $t->date('vencimento');
            $t->unsignedInteger('contact_id')->nullable();
            $t->string('status', 20)->default('sugerido');
            $t->unsignedInteger('titulo_id')->nullable();
            $t->unsignedInteger('decidido_por')->nullable();
            $t->dateTime('decidido_em')->nullable();
            $t->json('raw_payload')->nullable();
            $t->timestamps();
            $t->unique(['business_id', 'codigo_barras'], 'fin_dda_boletos_biz_codigo_uq');
            $t->index(['business_id', 'status']);
            $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_dda_boletos');
    }
};
