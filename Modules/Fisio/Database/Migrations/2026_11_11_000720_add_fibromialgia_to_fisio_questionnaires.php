<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Questionários de fibromialgia (etapa 07 §4.1, P-03 — pesquisa clínica em fibromialgia).
 *
 *  - `fisio_questionnaires.instrument` ganha `acr2016`, `fiqr`, `pgic` e `sentar_levantar_30s`.
 *    Estar no enum não libera: cada um só aparece e aceita resposta se estiver em
 *    `config('fisio.instrumentos_liberados')` (desligado em produção até a licença, ADR 0437 §8).
 *  - `instrument_version`: a versão do instrumento gravada junto da resposta, para o estudo não
 *    misturar versões. Nula nas respostas antigas (a definição delas está em `answers.definicao`).
 *
 * Aditiva e idempotente. O down() não tira valor do enum: a tabela é dado clínico sem exclusão e
 * pode já ter respostas dos instrumentos novos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fisio_questionnaires')) {
            return;
        }

        Schema::table('fisio_questionnaires', function (Blueprint $t) {
            $t->enum('instrument', ['eva', 'rmdq', 'psfs', 'lefs', 'spadi', 'acr2016', 'fiqr', 'pgic', 'sentar_levantar_30s'])->change(); // igual ao bloco de memory/dominio/fisio.md (o dominio:check lê o literal)
        });

        if (! Schema::hasColumn('fisio_questionnaires', 'instrument_version')) {
            Schema::table('fisio_questionnaires', function (Blueprint $t) {
                $t->string('instrument_version', 40)->nullable()->after('instrument')->comment('versão do instrumento usada na resposta (CatalogoQuestionarios)');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fisio_questionnaires') && Schema::hasColumn('fisio_questionnaires', 'instrument_version')) {
            Schema::table('fisio_questionnaires', function (Blueprint $t) {
                $t->dropColumn('instrument_version');
            });
        }
    }
};
