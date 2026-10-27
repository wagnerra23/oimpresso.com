<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estoque do ERP para a loja (ADR 0443, fatia d · D2 da ADR 0439): chave POR CONTA, desligada.
 * Mesmo com a flag geral da Nuvemshop ligada, a conta só recebe estoque depois que a empresa
 * escolhe enviar. Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mkt_account_credentials') && ! Schema::hasColumn('mkt_account_credentials', 'enviar_estoque')) {
            Schema::table('mkt_account_credentials', function (Blueprint $t) {
                $t->boolean('enviar_estoque')->default(false)->after('location_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('mkt_account_credentials', 'enviar_estoque')) {
            Schema::table('mkt_account_credentials', fn (Blueprint $t) => $t->dropColumn('enviar_estoque'));
        }
    }
};
