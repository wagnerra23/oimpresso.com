<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR ARQ-0005 (NfeBrasil) · Bridge nfe_fiscal_rules ↔ tax_rates.
 *
 * Mapeia 1:1 cada FiscalRule pra uma TaxRate derivada (`source` da TaxRate
 * é "nfe_fiscal_rule" via consulta nesta tabela bridge — não toca schema
 * core de tax_rates pra preservar compat com upstream UPos).
 *
 * Connector e outros módulos UPos lêem `tax_rates` normalmente (zero mudança).
 * UI de tributação NfeBrasil é a fonte de verdade quando módulo ativo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nfe_fiscal_rule_tax_rate_links')) {
            return; // idempotente — ADR tech/0008
        }

        Schema::create('nfe_fiscal_rule_tax_rate_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();

            $table->unsignedBigInteger('fiscal_rule_id')
                ->comment('FK nfe_fiscal_rules.id (módulo NfeBrasil)');
            $table->unsignedInteger('tax_rate_id')
                ->comment('FK tax_rates.id (core UPos — int unsigned, não bigint)');

            $table->timestamps();

            // Cada fiscal_rule mapeia exatamente 1 tax_rate
            $table->unique('fiscal_rule_id', 'nfe_fr_tr_links_fiscal_rule_unique');
            // Cada tax_rate "auto-gerada" volta pra exatamente 1 fiscal_rule
            $table->unique('tax_rate_id', 'nfe_fr_tr_links_tax_rate_unique');

            $table->index(['business_id', 'fiscal_rule_id'], 'nfe_fr_tr_links_biz_rule_idx');

            // FK pra fiscal_rules sobrevive ON DELETE CASCADE (motor é dono do mapping)
            $table->foreign('fiscal_rule_id', 'nfe_fr_tr_links_fiscal_rule_fk')
                ->references('id')->on('nfe_fiscal_rules')
                ->onDelete('cascade');

            // FK pra tax_rates sobrevive ON DELETE CASCADE — se tax_rate sumir
            // (delete manual via UI core), o link some também
            $table->foreign('tax_rate_id', 'nfe_fr_tr_links_tax_rate_fk')
                ->references('id')->on('tax_rates')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfe_fiscal_rule_tax_rate_links');
    }
};
