<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Origem da mercadoria por produto — tabela A da NF-e (`orig`, 0–8).
 *
 * As emissões montavam `orig = 0` para todo item. A coluna nasce com DEFAULT 0, então
 * todo produto que já existe continua saindo exatamente como saía: nada muda nas notas
 * até alguém escolher outra origem no cadastro.
 *
 * Contrato: AR-PROD-128 (o Office Comercial tinha `PRODUTO.ORIGEM_MERCADORIA`).
 * `products` já carrega `business_id` (Tier 0, ADR 0093) — a coluna é atributo do produto.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('products', 'origem_mercadoria')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedTinyInteger('origem_mercadoria')->default(0)
                ->comment('Origem da mercadoria, tabela A da NF-e (0-8)');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('products', 'origem_mercadoria')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('origem_mercadoria');
        });
    }
};
