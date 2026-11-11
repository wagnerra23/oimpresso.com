<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estudo de pesquisa e consentimento de pesquisa POR ESTUDO (etapa 07 P-02, ADR 0437 §7).
 *
 * Antes havia uma finalidade só, `pesquisa_ia`, que juntava "participar de um estudo" com
 * "treinar IA". Agora:
 * - `pesquisa` aponta para um estudo (`fisio_consents.study_id`) e a versão gravada é a do
 *   TCLE aprovado pelo CEP daquele estudo (`fisio_studies.tcle_version`);
 * - `treino_ia` é outra finalidade, na lista da config, sem estudo.
 *
 * As linhas antigas de `pesquisa_ia` ficam como estão (prova do que foi aceito). A finalidade
 * saiu da config, então elas não valem para nada: ninguém vira participante nem ganha
 * `treino_ia` por causa delas.
 *
 * Aditiva e idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fisio_studies')) {
            Schema::create('fisio_studies', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->string('title', 200);
                $t->string('cep_opinion_number', 60)->comment('número do parecer do CEP');
                $t->string('protocol_version', 30);
                $t->string('tcle_version', 30)->comment('versão do TCLE aprovada pelo CEP; o consentimento grava esta');
                $t->date('starts_on');
                $t->date('ends_on')->nullable();
                $t->unsignedInteger('responsible_user_id')->nullable()->comment('pesquisador responsável');
                $t->string('status', 20)->default('ativo')->comment('rascunho | ativo | encerrado');
                $t->unsignedInteger('created_by')->nullable();
                $t->timestamps();
                $t->foreign('business_id')->references('id')->on('business');
            });
        }

        if (Schema::hasTable('fisio_consents') && ! Schema::hasColumn('fisio_consents', 'study_id')) {
            Schema::table('fisio_consents', function (Blueprint $t) {
                $t->foreignId('study_id')->nullable()->after('purpose')->constrained('fisio_studies')
                    ->comment('só na finalidade pesquisa');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fisio_consents') && Schema::hasColumn('fisio_consents', 'study_id')) {
            Schema::table('fisio_consents', function (Blueprint $t) {
                $t->dropConstrainedForeignId('study_id');
            });
        }
        Schema::dropIfExists('fisio_studies');
    }
};
