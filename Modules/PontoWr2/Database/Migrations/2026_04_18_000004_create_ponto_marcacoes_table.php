<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Marcações de ponto — APPEND-ONLY.
 * Exigência Portaria MTP 671/2021: imutabilidade após janela de correção.
 * Para anular, emitir nova marcação com origem='ANULACAO' apontando para original.
 */
class CreatePontoMarcacoesTable extends Migration
{
    public function up()
    {
        Schema::create('ponto_marcacoes', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->integer('business_id')->unsigned()->index();
            $table->integer('colaborador_config_id')->unsigned()->index();
            $table->char('rep_id', 36)->nullable()->index();
            $table->bigInteger('nsr')->unsigned()->comment('Número Sequencial de Registro por REP/origem');
            $table->dateTime('momento')->comment('Data/hora da marcação — imutável');
            $table->enum('origem', [
                'REP_P',        // marcação no equipamento
                'AFD',          // importação AFD
                'AFDT',         // importação AFDT
                'MANUAL',       // lançamento manual autorizado
                'INTEGRACAO',   // API externa (eSocial, folha, etc.)
                'ANULACAO',     // anulação de marcação anterior
            ]);
            $table->enum('tipo', ['ENTRADA', 'SAIDA', 'ALMOCO_INICIO', 'ALMOCO_FIM', 'INTERCORRENCIA']);
            $table->char('marcacao_anulada_id', 36)->nullable()->comment('Preenchido quando origem=ANULACAO');
            $table->string('dispositivo_id', 64)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('ip', 45)->nullable();
            $table->char('hash_anterior', 64)->nullable()->comment('SHA-256 da marcação anterior no REP');
            $table->char('hash', 64)->comment('SHA-256 desta marcação (encadeamento)');
            $table->text('assinatura_digital')->nullable()->comment('PKCS#7 com certificado ICP A1');
            $table->integer('usuario_criador_id')->unsigned();
            $table->timestamp('created_at')->useCurrent();
            // Sem updated_at — append-only

            $table->unique(['rep_id', 'nsr']);
            $table->index(['business_id', 'colaborador_config_id', 'momento']);
            $table->index('marcacao_anulada_id'); // para buscas reversas
            $table->foreign('business_id')->references('id')->on('business');
            $table->foreign('colaborador_config_id')->references('id')->on('ponto_colaborador_config');
            $table->foreign('rep_id')->references('id')->on('ponto_reps');
            // FK auto-referente (marcacao_anulada_id → ponto_marcacoes.id) omitida.
            // Motivo: MySQL rejeita FK self-reference em char(36) com charset/collation da
            // base quando criada inline no Schema::create (errno 150). A integridade
            // é garantida em camada de aplicação (MarcacaoService::anular valida que
            // o ID referenciado existe antes de inserir a marcação com origem=ANULACAO)
            // e pelos triggers de imutabilidade logo abaixo.
            $table->foreign('usuario_criador_id')->references('id')->on('users');
        });

        // Trigger de imutabilidade — bloqueia UPDATE
        DB::unprepared(<<<SQL
            CREATE TRIGGER trg_ponto_marcacoes_no_update
            BEFORE UPDATE ON ponto_marcacoes
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'ponto_marcacoes é append-only (Portaria 671/2021). Use origem=ANULACAO.';
            END;
        SQL);

        // Trigger de imutabilidade — bloqueia DELETE
        DB::unprepared(<<<SQL
            CREATE TRIGGER trg_ponto_marcacoes_no_delete
            BEFORE DELETE ON ponto_marcacoes
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'ponto_marcacoes é append-only (Portaria 671/2021).';
            END;
        SQL);
    }

    public function down()
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_ponto_marcacoes_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_ponto_marcacoes_no_delete');
        Schema::dropIfExists('ponto_marcacoes');
    }
}
