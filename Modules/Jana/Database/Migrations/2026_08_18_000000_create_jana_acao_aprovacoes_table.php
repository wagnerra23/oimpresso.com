<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * jana_acao_aprovacoes — ledger das ações do Painel aprovadas por gente (HITL).
 *
 * Ordem 1 do `memory/requisitos/Jana/Index-visual-comparison.md` (§Resumo): até
 * 2026-08-18 TODO CTA da seção "Ações que … sugere" era decorativo — sem `onClick`,
 * com `title="(HITL — em breve V2)"`. Esta tabela é o backend que aquela linha
 * declarava como trava.
 *
 * ESCOPO DESTE PR: registra a APROVAÇÃO. O disparo (WhatsApp/e-mail) e a fila
 * `/ia/acoes` são PR próprio — é por isso que o CTA passa a dizer "Revisar", não
 * "Disparar" (charter §Anti-hooks: "prometer no botão o que a rota não entrega").
 *
 * `previa` e `contexto` são o RECIBO do que a pessoa viu no instante do OK. Eles
 * não se editam depois: mudar o gerador de prévia reescreveria o passado. O que
 * muda é `status`.
 *
 * Multi-tenant Tier 0 (ADR 0093): `business_id` NOT NULL — ação sugerida é sempre
 * de um business. Diferente de `jana_metas`, que admite NULL (meta da plataforma,
 * tenancy híbrida adr/arq/0001) — aqui não existe "aprovação da plataforma".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jana_acao_aprovacoes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id')->index();
            $table->unsignedInteger('user_id');
            // Chave da regra que gerou a ação (`JanaCockpit` §acoes / `AcaoHitlService::ACOES`):
            // regua-whatsapp · negociar-top · investigar-ticket · pix-adocao · preventivo-pendentes.
            $table->string('acao_key', 64);
            $table->string('status', 16)->default('aprovada'); // aprovada | recusada | executada
            $table->text('previa');
            $table->json('contexto')->nullable();
            $table->timestamp('aprovada_em')->nullable();
            $table->timestamps();

            // Nome explícito: `jana_acao_aprovacoes_business_id_acao_key_created_at_index`
            // tem 62 chars e passa raspando no teto de 64 do MySQL — a proibição canon
            // ("Identificadores MySQL >64 chars") manda nomear em vez de torcer.
            $table->index(['business_id', 'acao_key', 'created_at'], 'jana_acao_aprov_biz_key_idx');

            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jana_acao_aprovacoes');
    }
};
