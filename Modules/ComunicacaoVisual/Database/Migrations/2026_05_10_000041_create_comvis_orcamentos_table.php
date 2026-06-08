<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration comvis_orcamentos + comvis_orcamento_itens — orçamentos de comunicação visual.
 *
 * comvis_orcamentos: cabeçalho do orçamento (cliente, vendedor, totais, status).
 * comvis_orcamento_itens: linhas individuais com dimensões m² e cálculo de área.
 *
 * O campo area_m2 em cada item é calculado server-side: largura_m × altura_m × quantidade.
 * Cálculo implementado no Controller (frente C — US-COMVIS-001).
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * business_id em AMBAS as tabelas (redundante em itens mas obrigatório pro global scope).
 *
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md US-COMVIS-001
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
return new class extends Migration {
    public function up(): void
    {
        // --- Tabela principal: cabeçalho do orçamento ---
        Schema::create('comvis_orcamentos', function (Blueprint $table) {
            $table->bigIncrements('id');
            // int(10) unsigned pra bater com business.id (UltimatePOS legacy schema — FK constraint)
            $table->unsignedInteger('business_id');
            $table->string('numero', 20);                        // ORC-2026-00001 (gerado pelo Controller)
            $table->unsignedBigInteger('contato_id')->nullable(); // FK contacts.id — null = walk-in
            $table->unsignedInteger('vendedor_id')->nullable();   // FK users.id
            $table->date('data_emissao');
            $table->date('data_validade')->nullable();
            $table->enum('status', [
                'rascunho', 'enviado', 'aprovado', 'recusado', 'expirado',
            ])->default('rascunho');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('desconto', 15, 2)->default(0);
            $table->decimal('extras', 15, 2)->default(0);            // arte, edição, etc
            $table->decimal('custo_instalacao', 15, 2)->default(0);
            $table->decimal('custo_entrega', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->text('observacoes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['business_id', 'numero'], 'uq_comvis_orc_business_numero');
            $table->index('business_id', 'idx_comvis_orc_business');
            $table->index(['business_id', 'status'], 'idx_comvis_orc_business_status');
            $table->index('data_emissao', 'idx_comvis_orc_data_emissao');

            $table->foreign('business_id', 'fk_comvis_orc_business')
                  ->references('id')->on('business')->onDelete('cascade');
        });

        // --- Tabela filha: itens do orçamento ---
        Schema::create('comvis_orcamento_itens', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('orcamento_id');
            // int(10) unsigned pra bater com business.id (UltimatePOS legacy schema)
            $table->unsignedInteger('business_id');                // redundante, obrigatório pro global scope
            $table->unsignedBigInteger('material_id')->nullable(); // FK comvis_materiais — null = item livre
            $table->string('descricao', 255);
            $table->decimal('largura_m', 8, 3)->nullable();
            $table->decimal('altura_m', 8, 3)->nullable();
            $table->integer('quantidade')->default(1);
            $table->decimal('area_m2', 10, 3)->nullable();          // calculado server-side: largura × altura × qtd
            $table->decimal('preco_unitario_m2', 10, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->text('observacoes')->nullable();
            $table->integer('ordem')->default(0);
            $table->timestamps();

            $table->index('orcamento_id', 'idx_comvis_orc_itens_orc');
            $table->index('business_id', 'idx_comvis_orc_itens_business');

            $table->foreign('orcamento_id', 'fk_comvis_orc_itens_orcamento')
                  ->references('id')->on('comvis_orcamentos')->onDelete('cascade');

            $table->foreign('material_id', 'fk_comvis_orc_itens_material')
                  ->references('id')->on('comvis_materiais')->onDelete('set null');
        });
    }

    public function down(): void
    {
        // Ordem inversa para respeitar FKs
        Schema::dropIfExists('comvis_orcamento_itens');
        Schema::dropIfExists('comvis_orcamentos');
    }
};
