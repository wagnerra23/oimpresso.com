<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NSR ORIGINAL do arquivo AFD, na marcação importada (thread 12 do playbook Ponto, [W] 2026-09-30).
 *
 * O `nsr` da marcação é o contador INTERNO do REP (NsrService::proximo, na ordem da importação) —
 * não o NSR que o relógio gravou no AFD. O AEJ da Portaria MTP 671/2021 cita a marcação pelo NSR
 * do REP; sem o original, ele citaria o número errado para tudo que veio de relógio físico.
 *
 * Só para frente: `ponto_marcacoes` é append-only (os triggers barram UPDATE), então as marcações
 * já importadas ficam com `nsr_origem` nulo. Um ADD COLUMN nulo não dispara trigger nem altera
 * linha existente. Recuperar as antigas exige reimportar o arquivo original, que o dedup do
 * importador ignora — decisão à parte.
 */
class AddNsrOrigemToPontoMarcacoes extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('ponto_marcacoes') || Schema::hasColumn('ponto_marcacoes', 'nsr_origem')) {
            return;
        }

        Schema::table('ponto_marcacoes', function (Blueprint $table) {
            // Sem ->after(): coluna no FIM permite ALGORITHM=INSTANT (mesmo motivo do motivo_anulacao).
            $table->unsignedBigInteger('nsr_origem')->nullable()
                ->comment('NSR gravado pelo REP no AFD importado — só em origem=AFD/AFDT, no INSERT');
        });
    }

    public function down()
    {
        if (Schema::hasTable('ponto_marcacoes') && Schema::hasColumn('ponto_marcacoes', 'nsr_origem')) {
            Schema::table('ponto_marcacoes', function (Blueprint $table) {
                $table->dropColumn('nsr_origem');
            });
        }
    }
}
