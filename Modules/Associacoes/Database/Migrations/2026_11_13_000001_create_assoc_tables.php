<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fundo do Modules/Associacoes (ADR 0451, onda 2).
 *
 * Regras fixadas no banco:
 *  - Toda tabela tem `business_id` NOT NULL, indexado e com FK (ADR 0093). A rede inteira é UMA
 *    empresa (D1): as associações-membro são linhas, não empresas.
 *  - Associada é um `contact` do núcleo (sem CPF próprio, D5) e pertence a UMA associação.
 *  - `assoc_consentimentos` guarda a prova do consentimento LGPD (D5): o banco recusa DELETE;
 *    revogar preenche `revoked_at`.
 *  - Documento da associação não se apaga: sai da lista com `removido_em` + motivo (D3).
 */
return new class extends Migration
{
    private const TABELAS = ['assoc_consentimentos', 'assoc_associadas', 'assoc_documentos', 'assoc_associacoes'];

    public function up(): void
    {
        if (! Schema::hasTable('assoc_associacoes')) {
            Schema::create('assoc_associacoes', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->string('nome', 191);
                $t->string('sigla', 30)->nullable();
                $t->string('cidade', 120);
                $t->string('regiao', 120)->nullable();
                $t->string('cnpj', 18)->nullable();
                $t->unsignedInteger('representante_contact_id')->nullable();
                $t->string('situacao', 20)->default('ativa')->comment('em_adesao|ativa|desligada');
                $t->date('diretoria_atualizada_em')->nullable()->comment('data da ata da diretoria vigente');
                $t->date('diretoria_mudou_em')->nullable()->comment('mudança avisada, ata nova ainda não chegou (D7)');
                $t->timestamps();
                $t->unique(['business_id', 'nome'], 'assoc_assoc_biz_nome_unique');
                $t->foreign('business_id')->references('id')->on('business');
                $t->foreign('representante_contact_id')->references('id')->on('contacts')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('assoc_documentos')) {
            Schema::create('assoc_documentos', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->unsignedBigInteger('associacao_id');
                $t->string('tipo', 30)->comment('estatuto|ata_diretoria|certidao|outro');
                $t->string('descricao', 191)->nullable();
                $t->unsignedBigInteger('arquivo_id')->nullable()->comment('arquivo no Modules/Arquivos');
                $t->date('validade')->nullable();
                $t->dateTime('enviado_em');
                $t->unsignedInteger('enviado_por')->nullable();
                $t->dateTime('removido_em')->nullable();
                $t->string('removido_motivo', 255)->nullable();
                $t->unsignedInteger('removido_por')->nullable();
                $t->timestamps();
                $t->index(['business_id', 'associacao_id', 'tipo'], 'assoc_doc_biz_assoc_tipo_idx');
                $t->foreign('business_id')->references('id')->on('business');
                $t->foreign('associacao_id')->references('id')->on('assoc_associacoes');
            });
        }

        if (! Schema::hasTable('assoc_associadas')) {
            Schema::create('assoc_associadas', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->unsignedBigInteger('associacao_id');
                $t->unsignedInteger('contact_id');
                $t->date('entrou_em');
                $t->string('situacao', 20)->default('ativa')->comment('ativa|desligada');
                $t->date('desligada_em')->nullable();
                $t->timestamps();
                $t->unique(['business_id', 'contact_id'], 'assoc_assda_biz_contact_unique');
                $t->index(['business_id', 'associacao_id', 'situacao'], 'assoc_assda_biz_assoc_sit_idx');
                $t->foreign('business_id')->references('id')->on('business');
                $t->foreign('associacao_id')->references('id')->on('assoc_associacoes');
                $t->foreign('contact_id')->references('id')->on('contacts');
            });
        }

        if (! Schema::hasTable('assoc_consentimentos')) {
            Schema::create('assoc_consentimentos', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->unsignedBigInteger('associada_id');
                $t->string('finalidade', 40)->comment('lista fechada em config(associacoes.consentimento_versoes)');
                $t->string('versao', 20);
                $t->dateTime('concedido_em');
                $t->string('canal', 20)->comment('cadastro|app');
                $t->dateTime('revoked_at')->nullable();
                $t->timestamps();
                $t->index(['business_id', 'associada_id', 'finalidade'], 'assoc_cons_biz_assda_fin_idx');
                $t->foreign('business_id')->references('id')->on('business');
                $t->foreign('associada_id')->references('id')->on('assoc_associadas');
            });
        }

        // Trigger só existe em MySQL; na lane sqlite o contrato do módulo faz skip (ADR 0358).
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_assoc_consentimentos_no_delete');
            DB::unprepared("CREATE TRIGGER trg_assoc_consentimentos_no_delete BEFORE DELETE ON assoc_consentimentos FOR EACH ROW
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'assoc_consentimentos e prova LGPD: revogar preenche revoked_at, nunca apaga.'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_assoc_consentimentos_no_delete');
        }
        foreach (self::TABELAS as $tabela) {
            Schema::dropIfExists($tabela);
        }
    }
};
