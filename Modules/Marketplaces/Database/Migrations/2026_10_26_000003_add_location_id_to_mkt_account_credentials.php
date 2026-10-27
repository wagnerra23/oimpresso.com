<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Local de venda de cada conta do marketplace (US-MKT-007 · decisão [W] 2026-10-26: "escolhido
 * ao conectar a conta"). Nulo = a conta ainda não diz onde a venda entra, e o pedido NÃO vira
 * venda até alguém escolher. Coluna nova e nula: não muda nada que já existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mkt_account_credentials') && ! Schema::hasColumn('mkt_account_credentials', 'location_id')) {
            Schema::table('mkt_account_credentials', function (Blueprint $t) {
                $t->unsignedInteger('location_id')->nullable()->after('seller_nickname');
                $t->foreign('location_id', 'mkt_acc_location_fk')->references('id')->on('business_locations');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('mkt_account_credentials', 'location_id')) {
            Schema::table('mkt_account_credentials', function (Blueprint $t) {
                $t->dropForeign('mkt_acc_location_fk');
                $t->dropColumn('location_id');
            });
        }
    }
};
