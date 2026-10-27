<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-FIN-082 — link do portal do cliente (títulos em aberto, segunda via, histórico).
 *
 * Uma linha por link gerado, por cliente. Só o sha256 do token fica no banco; o token sai uma vez,
 * para o operador mandar ao cliente. Gerar outro link do mesmo cliente fecha o anterior.
 *
 * Tier 0 (ADR 0093): `business_id` com índice e FK; toda leitura filtra por ele e pelo `contact_id`.
 * Idempotente: só cria se a tabela não existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fin_portal_cliente_links')) {
            return;
        }

        Schema::create('fin_portal_cliente_links', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->index('fin_portal_biz_idx');
            $t->unsignedInteger('contact_id');
            $t->char('token_hash', 64)->unique('fin_portal_token_uq');
            $t->dateTime('expira_em');
            $t->unsignedInteger('created_by')->nullable();
            $t->timestamps();

            $t->index(['business_id', 'contact_id'], 'fin_portal_biz_contact_idx');
            $t->foreign('business_id', 'fin_portal_biz_fk')->references('id')->on('business')->cascadeOnDelete();
            $t->foreign('contact_id', 'fin_portal_contact_fk')->references('id')->on('contacts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_portal_cliente_links');
    }
};
