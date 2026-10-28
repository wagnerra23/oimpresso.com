<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cupom de desconto por código — US-SELL-076 (lacunas de Vendas, [W] 2026-10-27).
 *
 * Código único por empresa, % ou valor, validade, limite de uso, valor mínimo da compra e se
 * acumula com a promoção (`discounts`, que já cobre produto, categoria, marca e período).
 * Não altera `discounts` nem `transactions`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sell_coupons')) {
            return;
        }

        Schema::create('sell_coupons', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedInteger('business_id');
            $t->string('codigo', 40);
            $t->string('tipo', 20)->default('percentual'); // percentual | valor
            $t->decimal('valor', 15, 4)->default(0);
            $t->decimal('valor_minimo', 15, 4)->nullable();
            $t->dateTime('valido_de')->nullable();
            $t->dateTime('valido_ate')->nullable();
            $t->unsignedInteger('limite_usos')->nullable();
            $t->unsignedInteger('usos')->default(0);
            $t->boolean('acumula_promocao')->default(false);
            $t->boolean('ativo')->default(true);
            $t->unsignedInteger('created_by')->nullable();
            $t->timestamps();

            $t->unique(['business_id', 'codigo'], 'sell_coupons_biz_codigo_uq');
            $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sell_coupons');
    }
};
