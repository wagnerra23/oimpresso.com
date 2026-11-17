<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Local padrão de armazenagem do produto (CU-PROD-20, decisão [W] de 2026-11-06, #10544).
 *
 * É o local que a compra, a transferência e a separação vão sugerir já preenchido; a venda segue
 * baixando do local onde é feita, então nenhuma regra de estoque muda. Nulo: nenhum produto que já
 * existe muda. O local tem de ser do mesmo negócio e um dos locais de armazenagem do produto
 * (`product_racks`); quem confere é o ProductController, antes de gravar.
 *
 * `products` já carrega `business_id` (Tier 0, ADR 0093). A FK aponta para `business_locations` e
 * vira nula se o local for apagado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('products', 'local_padrao_id')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('local_padrao_id')->nullable()
                ->comment('Local de armazenagem sugerido na compra, transferência e separação (CU-PROD-20)');
            $table->foreign('local_padrao_id', 'products_local_padrao_id_fk')
                ->references('id')->on('business_locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('products', 'local_padrao_id')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign('products_local_padrao_id_fk');
            $table->dropColumn('local_padrao_id');
        });
    }
};
