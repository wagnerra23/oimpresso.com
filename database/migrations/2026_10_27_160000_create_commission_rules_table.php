<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regra única de comissão — US-COMM-015 (lacunas de Vendas, [W] 2026-10-27).
 *
 * Uma linha = uma regra: quem ganha (papel vendedor ou técnico da OS), sobre o quê (escopo
 * vendedor, produto ou categoria) e quanto (percentual da linha ou valor por unidade).
 * Não altera `users.cmmsn_percent`, `transactions.commission_agent` nem `commission_split`:
 * sem regra, a comissão segue a de hoje (SPEC Comissao §0, princípio de não-duplicação).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('commission_rules')) {
            return;
        }

        Schema::create('commission_rules', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedInteger('business_id');
            $t->string('papel', 20)->default('vendedor');   // vendedor | tecnico
            $t->string('escopo', 20)->default('vendedor');  // vendedor | produto | categoria
            $t->unsignedInteger('user_id')->nullable();     // regra só deste vendedor/técnico
            $t->unsignedInteger('product_id')->nullable();
            $t->unsignedInteger('category_id')->nullable();
            $t->string('tipo', 20)->default('percentual');  // percentual | valor (por unidade)
            $t->decimal('valor', 15, 4)->default(0);
            $t->boolean('ativo')->default(true);
            $t->unsignedInteger('created_by')->nullable();
            $t->timestamps();

            $t->index(['business_id', 'papel', 'ativo'], 'comm_rules_biz_papel_idx');
            $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_rules');
    }
};
