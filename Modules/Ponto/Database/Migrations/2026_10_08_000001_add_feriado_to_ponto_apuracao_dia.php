<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feriado na apuração (D5 de [W] em 2026-09-29, emenda da ADR 0014 · issue #8200).
 *
 * O Ponto passa a ler o feriado cadastrado no HRM (`essentials_holidays`). Duas colunas:
 * - `feriado_id`: qual feriado cobriu o dia (nulo = dia comum). Sem FK de propósito: a tabela é de
 *   outro módulo, e apagar o feriado no HRM não pode travar nem apagar a apuração; a próxima
 *   reapuração corrige o vínculo sozinha.
 * - `he_feriado_minutos`: minutos trabalhados no feriado, a pagar em dobro (Lei 605/49 Art. 9 e
 *   Súmula 146 do TST). Separados de `he_diurna_minutos`/`he_noturna_minutos`, que são HE de 50%.
 *
 * ADD COLUMN com default: não toca linha existente além do default, e a apuração antiga fica com
 * feriado nulo e zero minutos até ser reapurada.
 */
class AddFeriadoToPontoApuracaoDia extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('ponto_apuracao_dia')) {
            return;
        }

        if (! Schema::hasColumn('ponto_apuracao_dia', 'feriado_id')) {
            Schema::table('ponto_apuracao_dia', function (Blueprint $table) {
                $table->integer('feriado_id')->unsigned()->nullable()
                    ->comment('essentials_holidays.id que cobriu o dia; nulo = dia comum');
            });
        }

        if (! Schema::hasColumn('ponto_apuracao_dia', 'he_feriado_minutos')) {
            Schema::table('ponto_apuracao_dia', function (Blueprint $table) {
                $table->smallInteger('he_feriado_minutos')->default(0)
                    ->comment('Minutos trabalhados em feriado (100%, Súmula 146 TST)');
            });
        }
    }

    public function down()
    {
        if (! Schema::hasTable('ponto_apuracao_dia')) {
            return;
        }

        foreach (['he_feriado_minutos', 'feriado_id'] as $coluna) {
            if (Schema::hasColumn('ponto_apuracao_dia', $coluna)) {
                Schema::table('ponto_apuracao_dia', function (Blueprint $table) use ($coluna) {
                    $table->dropColumn($coluna);
                });
            }
        }
    }
}
