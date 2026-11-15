<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estorno do custo médio (D5 da auditoria de 2026-11-05).
 *
 * O movimento de uma compra cancelada ou excluída fica guardado, marcado com `estornado_em`.
 * `ativo` vale 1 no movimento em vigor e NULL no estornado: o índice único (empresa, compra,
 * variação, ativo) segura a mesma compra entrar duas vezes e deixa ela entrar de novo depois de um
 * estorno, porque no MySQL NULL não conta como repetido.
 */
return new class extends Migration
{
    private const TABELA = 'compras_custo_medio_movimentos';

    private const UNICO_ANTIGO = 'compras_cm_mov_biz_tx_var_uq';

    private const UNICO_NOVO = 'compras_cm_mov_biz_tx_var_ativo_uq';

    public function up(): void
    {
        if (! Schema::hasColumn(self::TABELA, 'ativo')) {
            Schema::table(self::TABELA, function (Blueprint $t) {
                $t->unsignedTinyInteger('ativo')->nullable()->default(1)->after('custo_depois');
                $t->dateTime('estornado_em')->nullable()->after('ativo');
            });
        }

        if (! Schema::hasIndex(self::TABELA, self::UNICO_NOVO)) {
            Schema::table(self::TABELA, fn (Blueprint $t) => $t->unique(['business_id', 'transaction_id', 'variation_id', 'ativo'], self::UNICO_NOVO));
        }
        if (Schema::hasIndex(self::TABELA, self::UNICO_ANTIGO)) {
            Schema::table(self::TABELA, fn (Blueprint $t) => $t->dropUnique(self::UNICO_ANTIGO));
        }
    }

    public function down(): void
    {
        // O índice antigo não admite o movimento estornado ao lado do novo da mesma compra.
        DB::table(self::TABELA)->whereNull('ativo')->delete();

        if (! Schema::hasIndex(self::TABELA, self::UNICO_ANTIGO)) {
            Schema::table(self::TABELA, fn (Blueprint $t) => $t->unique(['business_id', 'transaction_id', 'variation_id'], self::UNICO_ANTIGO));
        }
        if (Schema::hasIndex(self::TABELA, self::UNICO_NOVO)) {
            Schema::table(self::TABELA, fn (Blueprint $t) => $t->dropUnique(self::UNICO_NOVO));
        }
        if (Schema::hasColumn(self::TABELA, 'ativo')) {
            Schema::table(self::TABELA, fn (Blueprint $t) => $t->dropColumn(['ativo', 'estornado_em']));
        }
    }
};
