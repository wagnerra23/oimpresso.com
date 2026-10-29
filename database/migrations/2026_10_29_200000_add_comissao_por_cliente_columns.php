<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P-156 — comissão do representante por cliente, liberada por parcela recebida.
 *
 * `contacts.comissao_representante_percentual`: o % que o representante ganha nas vendas para
 * este cliente (Delphi: `PESSOAS.COMISSAO_REPRESENTANTE`, 2% a 15%). NULL = o cliente não usa
 * a regra, e nada muda para ele.
 *
 * `transactions.comissao_cliente_percentual`: o % congelado na venda, no momento em que ela
 * nasce (Delphi: `VENDA.COMISSAO_REPRESENTANTE`). Mudar o % do cliente depois não muda venda
 * antiga: no Delphi do Sabor Brasil, 1.318 de 1.643 lançamentos usam o % da venda e só 1.105 o
 * % atual do cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contacts') && ! Schema::hasColumn('contacts', 'comissao_representante_percentual')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->decimal('comissao_representante_percentual', 5, 2)->nullable();
            });
        }
        if (Schema::hasTable('transactions') && ! Schema::hasColumn('transactions', 'comissao_cliente_percentual')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->decimal('comissao_cliente_percentual', 5, 2)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('transactions', 'comissao_cliente_percentual')) {
            Schema::table('transactions', fn (Blueprint $t) => $t->dropColumn('comissao_cliente_percentual'));
        }
        if (Schema::hasColumn('contacts', 'comissao_representante_percentual')) {
            Schema::table('contacts', fn (Blueprint $t) => $t->dropColumn('comissao_representante_percentual'));
        }
    }
};
