<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddOfficeImpressoFieldsToProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            // Alterar o tamanho de 'name' para 191 e 'descricao' para 300
            $table->string('name', 191)->change();
            $table->string('descricao', 300)->nullable()->change();            
            // Campos extras
            $table->string('descricao_nfe', 300)->nullable();
            $table->double('valor_compra')->nullable();
            $table->double('custo')->nullable();
            $table->double('margem')->nullable();
            $table->double('valor')->nullable();
            $table->double('comp')->nullable();
            $table->double('larg')->nullable();
            $table->double('espessura')->nullable();
            $table->double('calc_vatacado')->nullable();
            $table->double('calc_vprazo')->nullable();
            $table->double('calc_vpor_peca')->nullable();
            $table->double('calc_pcompra_extra')->nullable();
            $table->double('calc_vcompra_extra')->nullable();
            $table->double('calc_vcompra_total')->nullable();
            $table->double('calc_pvenda_extra')->nullable();
            $table->double('calc_vvenda_extra')->nullable();
            $table->double('calc_vvenda_custo_minimo')->nullable();
            $table->double('calc_vvenda_custo')->nullable();
            $table->double('calc_vvenda_custo_total')->nullable();
            $table->double('calc_venda_minimo_valor')->nullable();
            $table->double('calc_venda_minimo_quant')->nullable();
            $table->string('codnf_ncm', 30)->nullable();
            $table->string('codfabrica', 60)->nullable();
            $table->string('codigoean', 60)->nullable();
            $table->integer('codproduto_tipo')->default(1);
            $table->integer('codproduto_marca')->nullable();
            $table->integer('codgrade_modelo')->nullable();
            $table->string('local', 30)->nullable();
            $table->double('estoque_min')->nullable();
            $table->double('estoque_max')->nullable();
            $table->double('calc_qpeso_bruto')->nullable();
            $table->double('calc_qpeso_liquido')->nullable();
            $table->char('tem_variacao', 1)->nullable();
            $table->char('tem_patrimonio', 1)->nullable();
            $table->char('tem_personalizado', 1)->nullable();
            $table->char('oimpresso_ativo', 1)->nullable();
            $table->string('oimpresso_codigo', 15)->nullable();
            $table->timestamp('oimpresso_dt_alteracao')->nullable();
            $table->integer('dias_para_comprar_min')->nullable();
            $table->integer('dias_para_comprar_max')->nullable();
            $table->timestamp('oimpresso_updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
             // Reverter para os valores originais
             $table->string('name', 255)->change(); // Valor padrão no Laravel
             $table->string('descricao', 255)->nullable()->change();       
            // Removendo os campos adicionados
            $table->dropColumn([
                'descricao_nfe', 'valor_compra', 'custo', 'margem', 'valor',
                'comp', 'larg', 'espessura', 'calc_vatacado', 'calc_vprazo',
                'calc_vpor_peca', 'calc_pcompra_extra', 'calc_vcompra_extra',
                'calc_vcompra_total', 'calc_pvenda_extra', 'calc_vvenda_extra',
                'calc_vvenda_custo_minimo', 'calc_vvenda_custo', 'calc_vvenda_custo_total',
                'calc_venda_minimo_valor', 'calc_venda_minimo_quant', 'codnf_ncm',
                'codfabrica', 'codigoean', 'codproduto_tipo', 'codproduto_marca',
                'codgrade_modelo', 'local', 'estoque_min', 'estoque_max',
                'calc_qpeso_bruto', 'calc_qpeso_liquido', 'ativo',
                'tem_variacao', 'tem_patrimonio', 'tem_personalizado',
                'oimpresso_ativo', 'oimpresso_codigo', 'oimpresso_dt_alteracao',
                'dias_para_comprar_min', 'dias_para_comprar_max', 'oimpresso_updated_at'
            ]);
        });
    }
}
