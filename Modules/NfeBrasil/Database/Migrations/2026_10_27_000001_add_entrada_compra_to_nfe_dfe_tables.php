<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entrada por XML de compra — fase 1 (ADR `proposals/2026-10-06-entrada-xml-compra.md` D2/D3 ·
 * US-COM-003 R-COM-204/205).
 *
 * - `nfe_dfe_recebidos.transaction_id`: a compra criada a partir da nota. UNIQUE com
 *   `business_id` deixa a reimportação idempotente (NULL repete à vontade no MySQL).
 * - `nfe_dfe_recebidos.origem`: `nsu` (SEFAZ) ou `upload` (arquivo). Upload não tem NSU, por
 *   isso `nsu` passa a aceitar nulo.
 * - `nfe_dfe_itens`: o que falta para o de-para e para o fiscal — código do item no fornecedor
 *   (`cProd`), EAN (`cEAN`), unidade (`uCom`), CST/CSOSN, ICMS destacado e a variação casada
 *   (`variation_id`, não `product_id`: no UltimatePOS o estoque é por variação).
 *
 * Tier 0 (ADR 0093): as duas tabelas já têm `business_id` indexado; nada aqui cruza empresa.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nfe_dfe_recebidos') && ! Schema::hasColumn('nfe_dfe_recebidos', 'transaction_id')) {
            Schema::table('nfe_dfe_recebidos', function (Blueprint $t) {
                $t->unsignedInteger('transaction_id')->nullable()->after('xml_path')
                    ->comment('Compra (transactions.type=purchase) criada a partir desta nota');
                $t->enum('origem', ['nsu', 'upload'])->default('nsu')->after('transaction_id');
                $t->unsignedBigInteger('nsu')->nullable()->change();
                $t->unique(['business_id', 'transaction_id'], 'nfe_dfe_recebidos_biz_tx_uq');
                $t->foreign('transaction_id', 'nfe_dfe_recebidos_tx_fk')
                    ->references('id')->on('transactions')->nullOnDelete();
            });
        }

        if (Schema::hasTable('nfe_dfe_itens') && ! Schema::hasColumn('nfe_dfe_itens', 'codigo_fornecedor')) {
            Schema::table('nfe_dfe_itens', function (Blueprint $t) {
                $t->string('codigo_fornecedor', 60)->nullable()->after('dfe_recebido_id');
                $t->string('ean', 14)->nullable()->after('codigo_fornecedor');
                $t->string('unidade', 6)->nullable()->after('ean');
                $t->string('cst_csosn', 3)->nullable()->after('cfop');
                $t->decimal('icms_valor', 15, 2)->nullable()->after('valor_total');
                $t->unsignedInteger('variation_id')->nullable()->after('icms_valor');
                $t->foreign('variation_id', 'nfe_dfe_itens_variation_fk')
                    ->references('id')->on('variations')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('nfe_dfe_itens', 'codigo_fornecedor')) {
            Schema::table('nfe_dfe_itens', function (Blueprint $t) {
                $t->dropForeign('nfe_dfe_itens_variation_fk');
                $t->dropColumn(['codigo_fornecedor', 'ean', 'unidade', 'cst_csosn', 'icms_valor', 'variation_id']);
            });
        }

        if (Schema::hasColumn('nfe_dfe_recebidos', 'transaction_id')) {
            Schema::table('nfe_dfe_recebidos', function (Blueprint $t) {
                $t->dropForeign('nfe_dfe_recebidos_tx_fk');
                $t->dropUnique('nfe_dfe_recebidos_biz_tx_uq');
                $t->dropColumn(['transaction_id', 'origem']);
            });
        }
    }
};
