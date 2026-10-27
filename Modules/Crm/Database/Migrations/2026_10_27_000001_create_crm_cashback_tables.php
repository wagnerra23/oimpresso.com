<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cashback por regra (US-CRM-093).
 *
 * - `crm_cashback_regras`: uma regra por empresa (percentual, valor mínimo da venda, validade).
 * - `crm_cashback_creditos`: um crédito por venda. O valor sobe o saldo de Adiantamento do cliente
 *   (`contacts.balance`), o mesmo que o caixa já aceita como forma de pagamento (vale-troca, #9867).
 *   O registro guarda a origem e a validade, que o saldo sozinho não tem.
 *
 * Tier 0 (ADR 0093): `business_id` NOT NULL, indexado, FK para `business`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_cashback_regras')) {
            Schema::create('crm_cashback_regras', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('business_id')->unique();
                $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
                $table->decimal('percentual', 5, 2);
                $table->decimal('valor_minimo', 22, 4)->default(0);
                $table->unsignedSmallInteger('validade_dias')->default(90);
                $table->boolean('ativo')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('crm_cashback_creditos')) {
            Schema::create('crm_cashback_creditos', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('business_id')->index();
                $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
                $table->unsignedInteger('contact_id')->index();
                $table->unsignedInteger('transaction_id');
                $table->decimal('base', 22, 4);
                $table->decimal('percentual', 5, 2);
                $table->decimal('valor', 22, 4);
                $table->date('expira_em')->index();
                $table->string('situacao', 20)->default('ativo');
                $table->decimal('valor_baixado', 22, 4)->default(0);
                $table->timestamps();
                $table->unique(['business_id', 'transaction_id'], 'crm_cashback_creditos_venda_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_cashback_creditos');
        Schema::dropIfExists('crm_cashback_regras');
    }
};
