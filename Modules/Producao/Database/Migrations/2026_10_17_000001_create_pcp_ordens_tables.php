<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OS por item (ADR 0436, fase 2 da ADR 0435).
 *
 * - `pcp_ordens`: uma OS por item da venda que gera OS. A situação do item anda no motor FSM
 *   (`current_stage_id`, processo "OS e produção por item"); o roteiro anda nas programações.
 * - `pcp_produto_roteiro`: produto → roteiro, por empresa (UC-FLX-07). Não mexe em `products`.
 * - `pcp_programacoes` ganha a posição da etapa no roteiro (bloco, trilha, ordem, junta) e o
 *   posto passa a ser opcional (etapa de arte não tem máquina).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pcp_ordens')) {
            Schema::create('pcp_ordens', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->unsignedInteger('transaction_id');
                // Idempotência do "Cliente aprova": uma OS por linha da venda.
                $t->unsignedInteger('transaction_sell_line_id');
                $t->unsignedInteger('product_id');
                $t->unsignedInteger('variation_id')->nullable();
                $t->decimal('quantidade', 22, 4);
                $t->foreignId('roteiro_id')->nullable()->constrained('pcp_roteiros');
                $t->unsignedBigInteger('current_stage_id')->nullable();
                $t->dateTime('pronta_em')->nullable();
                $t->decimal('custo_real', 22, 4)->nullable();
                $t->dateTime('custo_congelado_em')->nullable();
                $t->unsignedInteger('created_by')->nullable();
                $t->timestamps();
                $t->unique(['business_id', 'transaction_sell_line_id'], 'pcp_ordens_biz_linha_uq');
                $t->index(['business_id', 'transaction_id'], 'pcp_ordens_biz_tx_idx');
                $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
                $t->foreign('current_stage_id')->references('id')->on('sale_process_stages')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('pcp_produto_roteiro')) {
            Schema::create('pcp_produto_roteiro', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->unsignedInteger('product_id');
                $t->foreignId('roteiro_id')->constrained('pcp_roteiros')->cascadeOnDelete();
                $t->timestamps();
                $t->unique(['business_id', 'product_id'], 'pcp_prod_rot_biz_prod_uq');
                $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('pcp_programacoes') && ! Schema::hasColumn('pcp_programacoes', 'bloco')) {
            Schema::table('pcp_programacoes', function (Blueprint $t) {
                $t->foreignId('roteiro_etapa_id')->nullable()->after('operacao_id')->constrained('pcp_roteiro_etapas')->nullOnDelete();
                $t->unsignedSmallInteger('bloco')->nullable()->after('roteiro_etapa_id');
                $t->unsignedTinyInteger('trilha')->default(0)->after('bloco');
                $t->unsignedSmallInteger('ordem')->default(0)->after('trilha');
                $t->string('nome', 120)->nullable()->after('ordem');
                $t->string('junta', 120)->nullable()->after('nome');
                $t->unsignedBigInteger('posto_id')->nullable()->change();
                $t->index(['business_id', 'os_tipo', 'os_id'], 'pcp_prog_biz_os_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pcp_programacoes') && Schema::hasColumn('pcp_programacoes', 'bloco')) {
            Schema::table('pcp_programacoes', function (Blueprint $t) {
                $t->dropIndex('pcp_prog_biz_os_idx');
                $t->dropConstrainedForeignId('roteiro_etapa_id');
                $t->dropColumn(['bloco', 'trilha', 'ordem', 'nome', 'junta']);
            });
        }
        Schema::dropIfExists('pcp_produto_roteiro');
        Schema::dropIfExists('pcp_ordens');
    }
};
