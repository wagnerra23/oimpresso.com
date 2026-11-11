<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dados sociais do paciente (US-FISIO-061), todos opcionais.
 *
 * Dado sensível (Lei 13.709/2018, art. 11: raça/cor, saúde). Tabela própria, 1:1 com o paciente,
 * para não viajar junto com o perfil clínico: nada daqui vai ao portal nem à exportação da
 * pesquisa. Serve ao relatório agregado da associação (US-FISIO-062), só contagem e média.
 *
 * Nascimento, município e bairro NÃO moram aqui: são do contato (`contacts.dob`, `city`,
 * `state`, `neighborhood`), o mesmo endereço do cadastro de clientes.
 *
 * Cada coluna de escolha guarda o código da lista fechada de `DadosSociais::OPCOES`, inclusive
 * `prefiro_nao_informar`. Nulo = ninguém perguntou.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fisio_patient_social')) {
            return;
        }

        Schema::create('fisio_patient_social', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->index();
            $t->foreignId('patient_id')->unique()->constrained('fisio_patients');
            $t->string('sexo', 30)->nullable();
            $t->string('genero', 30)->nullable();
            $t->string('raca_cor', 30)->nullable();
            $t->string('cns', 15)->nullable()->comment('Cartão Nacional de Saúde, 15 dígitos');
            $t->string('renda_familiar', 30)->nullable()->comment('faixa em salários mínimos');
            $t->string('cadunico', 30)->nullable();
            $t->string('situacao_trabalho', 30)->nullable();
            $t->string('escolaridade', 30)->nullable();
            $t->string('diagnostico_cid', 10)->nullable();
            $t->string('diagnostico_por', 30)->nullable();
            $t->date('diagnostico_em')->nullable();
            $t->string('sintomas_ha', 30)->nullable()->comment('faixa de tempo com sintomas');
            $t->json('comorbidades')->nullable();
            $t->string('comorbidades_outras', 255)->nullable();
            $t->string('como_chegou', 30)->nullable();
            $t->string('cipf', 30)->nullable()->comment('carteira da pessoa com fibromialgia');
            $t->unsignedInteger('updated_by')->nullable();
            $t->timestamps();
            $t->foreign('business_id')->references('id')->on('business');
            $t->foreign('updated_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fisio_patient_social');
    }
};
