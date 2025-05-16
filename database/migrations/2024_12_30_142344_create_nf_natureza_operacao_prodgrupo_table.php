<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNfNaturezaOperacaoProdgrupoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('nf_natureza_operacao_prodgrupo', function (Blueprint $table) {
            $table->increments('id'); 
            $table->unsignedBigInteger('business_id');
            $table->unsignedInteger('nf_natureza_operacao_id'); // Relacionamento
            $table->unsignedInteger('produto_grupo_id'); // Relacionamento
            $table->string('codnf_cst', 4)->nullable();
            $table->string('codnf_cfop', 9)->nullable();
            $table->string('codnf_cfop_fora', 9)->nullable();
            $table->double('picms')->nullable();
            $table->double('picmsst')->nullable();
            $table->double('pmvast')->nullable();
            $table->double('predbc')->nullable();
            $table->double('predbcst')->nullable();
            $table->string('pis_st', 4)->nullable();
            $table->string('cofins_st', 4)->nullable();
            $table->string('ipi_st', 4)->nullable();
            $table->double('ipi_vbc')->nullable();
            $table->double('ipi_qunid')->nullable();
            $table->double('ipi_vunid')->nullable();
            $table->double('ipi_pipi')->nullable();
            $table->double('ipi_vipi')->nullable();
            $table->double('ii_vbc')->nullable();
            $table->double('ii_vdespadu')->nullable();
            $table->double('ii_pii')->nullable();
            $table->double('ii_piof')->nullable();
            $table->double('pis_vbc')->nullable();
            $table->double('pis_ppis')->nullable();
            $table->double('pis_vpis')->nullable();
            $table->double('pis_qbcprod')->nullable();
            $table->double('pis_valiqprod')->nullable();
            $table->double('pisst_vbc')->nullable();
            $table->double('pisst_ppis')->nullable();
            $table->double('pisst_vpis')->nullable();
            $table->double('pisst_qbcprod')->nullable();
            $table->double('pisst_valiqprod')->nullable();
            $table->double('cofins_vbc')->nullable();
            $table->double('cofins_pcofins')->nullable();
            $table->double('cofins_vbcprod')->nullable();
            $table->double('cofins_valiqprod')->nullable();
            $table->double('cofins_vcofins')->nullable();
            $table->double('cofinsst_vbc')->nullable();
            $table->double('cofinsst_pcofins')->nullable();
            $table->double('cofinsst_qbcprod')->nullable();
            $table->double('cofinsst_valiqprod')->nullable();
            $table->double('cofinsst_vcofins')->nullable();
            $table->double('issqn_vbc')->nullable();
            $table->double('issqn_pvaliq')->nullable();
            $table->double('issqn_vissqn')->nullable();
            $table->double('issqn_cmunfg')->nullable();
            $table->double('issqn_listserv')->nullable();
            $table->double('ii_vii')->nullable();
            $table->double('ii_viof')->nullable();
            $table->double('issqn_valiq')->nullable();
            $table->string('icms_paf', 3)->nullable();
            $table->string('codnf_cfop_entrada', 9)->nullable();
            $table->string('codnf_cfop_entrada_fora', 9)->nullable();
            $table->char('mantem_online', 1)->nullable();
            $table->integer('icms_modbc')->nullable();
            $table->integer('icms_modbcst')->nullable();
            $table->char('pis_cofins_por_quant', 1)->nullable();
            $table->char('ipi_por_quant', 1)->nullable();
            $table->char('calcula_pis', 1)->nullable();
            $table->char('calcula_ipi', 1)->nullable();
            $table->char('calcula_cofins', 1)->nullable();
            $table->char('calcula_icms_st', 1)->nullable();
            $table->timestamp('dt_alteracao')->nullable();
            $table->char('calcula_icms', 1)->nullable();
            $table->integer('issqn_tipotributacao')->nullable();
            $table->double('nf_pcredsn')->nullable();
            $table->integer('servico_natureza_operacao')->nullable();
            $table->integer('servico_regime_especial_tribut')->nullable();
            $table->char('servico_incentivador_cultural', 1)->nullable();
            $table->integer('servico_iss_retido')->nullable();
            $table->double('servico_aliquota')->nullable();
            $table->char('calcula_ii', 1)->nullable();
            $table->string('referencia', 15)->nullable();
            $table->integer('ipi_cenq')->nullable();
            $table->string('codnf_cest', 7)->nullable();
            $table->integer('codvenda_tipo')->nullable();
            $table->integer('issqn_incentivador_cultural')->nullable();
            $table->double('comisao')->nullable();
            $table->char('vbcst_frete', 1)->nullable();
            $table->char('vbcst_ipi', 1)->nullable();
            $table->char('vbcst_confins', 1)->nullable();
            $table->char('vbcst_ii', 1)->nullable();
            $table->char('vbcst_pis', 1)->nullable();
            $table->char('vbc_frete', 1)->nullable();
            $table->char('vbc_ipi', 1)->nullable();
            $table->char('vbc_confins', 1)->nullable();
            $table->char('vbc_ii', 1)->nullable();
            $table->char('vbc_pis', 1)->nullable();
            $table->double('predmvast')->nullable();
            $table->char('calcula_issqn', 1)->nullable();
            $table->char('vbc_desconto', 1)->nullable();
            $table->char('vbcst_desconto', 1)->nullable();
            $table->char('nao_calcula_valor_iss', 1)->default('N');
            $table->char('ativo', 1)->default('S');
            $table->char('tem_diferimento', 1)->nullable();
            $table->double('pdif')->nullable();
            $table->string('cbenef', 50)->nullable();
            $table->string('operacao', 50)->nullable();
            $table->char('consumidor_final', 1)->default('N');
            $table->char('entrada_saida', 1)->nullable();
            $table->string('codplanocontas', 15)->nullable();
            $table->double('picms_nconsumidor_final')->nullable();

            // Campos adicionais
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('officeimpresso_dt_alteracao')->nullable();
            $table->integer('officeimpresso_codnf_natureza_operacao')->nullable();
            $table->string('officeimpresso_codproduto_grupo', 15)->nullable();
 
            $table->timestamps();


            // Chaves estrangeiras
            $table->foreign('nf_natureza_operacao_id')
            ->references('id')
            ->on('nf_natureza_operacao')
            ->onDelete('cascade');
  
            $table->foreign('produto_grupo_id')
                    ->references('id')
                    ->on('produto_grupo')
                    ->onDelete('cascade');           
        
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('nf_natureza_operacao_prodgrupo');
    }
}
