<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P-087 — a tabela de preço pode ficar no próprio cliente, além do grupo.
 *
 * Hoje a venda só acha a tabela pelo grupo (`contacts.customer_group_id` →
 * `customer_groups.selling_price_group_id`). No Sabor Brasil (Delphi, medido em 2026-10-29)
 * 87 dos 192 clientes com tabela também têm um grupo (`PESSOAS.CODPESSOAS_GRUPO`), e 48 das
 * 61 tabelas usadas têm um cliente só: com uma coluna de grupo, um dos dois se perderia.
 *
 * Preenchida, vale no lugar da tabela do grupo; NULL = segue o grupo (todo cliente de antes).
 * Sem FK física, como as outras colunas de vínculo de `contacts` (compat UltimatePOS). Quem
 * grava confere a tabela contra a empresa da sessão, e quem lê junta pela mesma empresa (ADR 0093).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contacts') || Schema::hasColumn('contacts', 'selling_price_group_id')) {
            return;
        }

        Schema::table('contacts', function (Blueprint $table) {
            $table->unsignedInteger('selling_price_group_id')->nullable();
            $table->index(['business_id', 'selling_price_group_id'], 'contacts_biz_spg_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('contacts', 'selling_price_group_id')) {
            return;
        }

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex('contacts_biz_spg_idx');
            $table->dropColumn('selling_price_group_id');
        });
    }
};
