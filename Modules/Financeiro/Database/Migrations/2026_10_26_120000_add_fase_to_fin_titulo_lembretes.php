<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-FIN-033 — aviso ANTES do vencimento na régua de cobrança.
 *
 * `etapa_dias` é unsigned e o UNIQUE era (titulo_id, etapa_dias): o lembrete de "vence em 3 dias"
 * colidiria com o de "venceu há 3 dias". A coluna `fase` (`antes`|`apos`) separa os dois; linhas
 * existentes ficam `apos`, que é o que elas são. O UNIQUE novo é criado antes de soltar o antigo,
 * porque a FK de `titulo_id` precisa de um índice que comece por ela o tempo todo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fin_titulo_lembretes') || Schema::hasColumn('fin_titulo_lembretes', 'fase')) {
            return;
        }

        Schema::table('fin_titulo_lembretes', function (Blueprint $t) {
            $t->string('fase', 10)->default('apos')->after('titulo_id');
        });
        Schema::table('fin_titulo_lembretes', function (Blueprint $t) {
            $t->unique(['titulo_id', 'fase', 'etapa_dias'], 'fin_tit_lembretes_titulo_fase_etapa_uq');
        });
        Schema::table('fin_titulo_lembretes', function (Blueprint $t) {
            $t->dropUnique('fin_tit_lembretes_titulo_etapa_uq');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('fin_titulo_lembretes', 'fase')) {
            return;
        }

        // Volta ao UNIQUE antigo só sem avisos "antes" — senão (titulo_id, etapa_dias) colide.
        \Illuminate\Support\Facades\DB::table('fin_titulo_lembretes')->where('fase', 'antes')->delete();
        Schema::table('fin_titulo_lembretes', function (Blueprint $t) {
            $t->unique(['titulo_id', 'etapa_dias'], 'fin_tit_lembretes_titulo_etapa_uq');
        });
        Schema::table('fin_titulo_lembretes', function (Blueprint $t) {
            $t->dropUnique('fin_tit_lembretes_titulo_fase_etapa_uq');
            $t->dropColumn('fase');
        });
    }
};
