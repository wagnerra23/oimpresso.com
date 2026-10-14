<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Playbook Fiscal thread 29 · natureza ICMS × ISS por produto e DIFAL do Simples por UF
 * (R-NFE-034 · R-NFE-035 · D-NATUREZA · D-DIFAL-UF, respondidas [W] 2026-10-06).
 *
 * - `nfe_naturezas_produto`: a natureza fiscal de um produto (mercadoria · servico · pendente),
 *   decidida pelo contador, com autor, data e fonte. Tabela-ponte: `products` (core UltimatePOS)
 *   não muda. Produto sem linha = mercadoria, como hoje.
 * - `nfe_icms_uf.cobra_difal_simples`: a UF de destino cobra DIFAL de optante do Simples por lei
 *   própria. Nasce false em todas as linhas: o padrão é não recolher.
 *
 * Só acrescenta (ADR 0093 G8): nenhuma linha existente muda.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nfe_naturezas_produto')) {
            Schema::create('nfe_naturezas_produto', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('business_id')->index();
                $table->unsignedInteger('product_id');
                $table->string('natureza', 12)->default('pendente')->comment('mercadoria · servico · pendente');
                $table->string('codigo_servico', 5)->nullable()->comment('Item da lista da LC 116/2003 (ex. 24.01)');
                $table->string('fonte', 255)->nullable()->comment('Base da decisão citada pelo contador');
                $table->unsignedInteger('decidido_por')->nullable()->comment('users.id de quem decidiu');
                $table->timestamp('decidido_em')->nullable();
                $table->timestamps();

                $table->unique(['business_id', 'product_id'], 'nfe_naturezas_produto_biz_prod_unique');
                $table->index(['business_id', 'natureza'], 'nfe_naturezas_produto_biz_nat_idx');
                $table->foreign('business_id', 'nfe_naturezas_produto_business_fk')
                    ->references('id')->on('business')->cascadeOnDelete();
                $table->foreign('product_id', 'nfe_naturezas_produto_product_fk')
                    ->references('id')->on('products')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('nfe_icms_uf') && ! Schema::hasColumn('nfe_icms_uf', 'cobra_difal_simples')) {
            Schema::table('nfe_icms_uf', function (Blueprint $table) {
                $table->boolean('cobra_difal_simples')->default(false)
                    ->comment('UF de destino cobra DIFAL do Simples por lei própria (STF Tema 1284)');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('nfe_icms_uf') && Schema::hasColumn('nfe_icms_uf', 'cobra_difal_simples')) {
            Schema::table('nfe_icms_uf', fn (Blueprint $table) => $table->dropColumn('cobra_difal_simples'));
        }

        Schema::dropIfExists('nfe_naturezas_produto');
    }
};
