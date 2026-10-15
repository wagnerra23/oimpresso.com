<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fundo do PCP (thread 02 + emenda E02 do playbook de Produção, ADR 0431).
 *
 * Toda tabela nasce com `business_id` indexado + FK (ADR 0093). `pcp_apontamentos` e
 * `pcp_eventos` são APPEND-ONLY por trigger, como `ponto_marcacoes`: abrir e fechar um
 * cronômetro são DUAS linhas (`tipo` inicio/fim), nunca um UPDATE; correção é lançamento novo.
 *
 * A OS fica referenciada por (`os_tipo`, `os_id`) sem FK: PCP é camada única e os módulos
 * (Repair, ComunicacaoVisual, OficinaAuto) seguem donos da OS (D-RELACAO).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pcp_turnos')) {
            Schema::create('pcp_turnos', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->unique();
                $t->time('inicio')->default('08:00:00');
                $t->time('fim')->default('18:00:00');
                $t->timestamps();
                $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('pcp_postos')) {
            Schema::create('pcp_postos', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->string('codigo', 40);
                $t->string('nome', 120);
                $t->string('setor', 60)->nullable();
                // E02: máquina e veículo custam pela hora da máquina; o resto pela hora da pessoa.
                $t->enum('tipo_custo', ['maquina', 'pessoa']);
                $t->boolean('veiculo')->default(false);
                $t->decimal('custo_hora', 12, 2)->default(0);
                $t->decimal('custo_km', 12, 2)->nullable();
                // D4: capacidade configurável por empresa.
                $t->decimal('capacidade_por_hora', 12, 3)->nullable();
                $t->string('unidade_capacidade', 10)->nullable();
                $t->decimal('horas_por_dia', 5, 2)->default(10);
                $t->boolean('ativo')->default(true);
                $t->timestamps();
                $t->unique(['business_id', 'codigo'], 'pcp_postos_biz_codigo_uq');
                $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('pcp_operacoes')) {
            Schema::create('pcp_operacoes', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->foreignId('posto_id')->constrained('pcp_postos');
                $t->string('codigo', 40);
                $t->string('nome', 120);
                $t->unsignedInteger('tempo_padrao_minutos')->default(0);
                $t->string('unidade', 10)->nullable();
                $t->boolean('ativo')->default(true);
                $t->timestamps();
                $t->unique(['business_id', 'codigo'], 'pcp_operacoes_biz_codigo_uq');
                $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('pcp_programacoes')) {
            Schema::create('pcp_programacoes', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->string('os_tipo', 40);
                $t->unsignedBigInteger('os_id');
                $t->foreignId('posto_id')->constrained('pcp_postos');
                $t->foreignId('operacao_id')->nullable()->constrained('pcp_operacoes');
                $t->dateTime('inicio_previsto');
                $t->dateTime('fim_previsto');
                $t->enum('status', ['pendente', 'iniciada', 'concluida', 'cancelada'])->default('pendente');
                $t->unsignedInteger('created_by');
                $t->timestamps();
                $t->index(['business_id', 'posto_id', 'inicio_previsto'], 'pcp_prog_biz_posto_inicio_idx');
                $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
                $t->foreign('created_by')->references('id')->on('users');
            });
        }

        if (! Schema::hasTable('pcp_apontamentos')) {
            Schema::create('pcp_apontamentos', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                // E02 / UC-PCV-23: a fila offline reenvia; a chave torna o reenvio inócuo.
                $t->string('chave_idempotencia', 64);
                $t->enum('tipo', ['inicio', 'fim']);
                $t->unsignedBigInteger('inicio_id')->nullable()->comment('linha de inicio que esta fecha');
                $t->string('os_tipo', 40)->nullable();
                $t->unsignedBigInteger('os_id')->nullable();
                $t->foreignId('posto_id')->constrained('pcp_postos');
                $t->foreignId('operacao_id')->nullable()->constrained('pcp_operacoes');
                $t->enum('tipo_custo', ['maquina', 'pessoa'])->comment('cópia do posto no início');
                $t->decimal('custo_hora', 12, 2)->comment('cópia do posto no início: custo não muda para trás');
                $t->unsignedBigInteger('maquina_id')->nullable()->comment('posto maquina: não ocupa a pessoa');
                $t->unsignedInteger('user_id')->nullable()->comment('posto pessoa: quem trabalhou');
                $t->unsignedInteger('iniciado_por');
                $t->dateTime('momento');
                $t->enum('origem', ['qr', 'manual', 'turno', 'ponto'])->default('qr');
                $t->text('justificativa')->nullable();
                $t->decimal('qtd_produzida', 12, 3)->nullable();
                $t->decimal('qtd_perdida', 12, 3)->nullable();
                $t->timestamp('created_at')->useCurrent();
                $t->unique(['business_id', 'chave_idempotencia'], 'pcp_apont_biz_chave_uq');
                $t->index(['business_id', 'os_tipo', 'os_id'], 'pcp_apont_biz_os_idx');
                $t->index('inicio_id');
                $t->foreign('business_id')->references('id')->on('business');
                $t->foreign('maquina_id')->references('id')->on('pcp_postos');
                $t->foreign('user_id')->references('id')->on('users');
                $t->foreign('iniciado_por')->references('id')->on('users');
            });
        }

        if (! Schema::hasTable('pcp_eventos')) {
            Schema::create('pcp_eventos', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->string('tipo', 40);
                $t->string('os_tipo', 40)->nullable();
                $t->unsignedBigInteger('os_id')->nullable();
                $t->foreignId('posto_id')->nullable()->constrained('pcp_postos');
                $t->unsignedInteger('user_id')->nullable();
                $t->json('dados')->nullable();
                $t->timestamp('created_at')->useCurrent();
                $t->index(['business_id', 'created_at'], 'pcp_eventos_biz_criado_idx');
                $t->foreign('business_id')->references('id')->on('business');
                $t->foreign('user_id')->references('id')->on('users');
            });
        }

        // Triggers só existem em MySQL; a lane sqlite não roda o contrato do fundo.
        if (DB::getDriverName() === 'mysql') {
            foreach (['pcp_apontamentos', 'pcp_eventos'] as $tabela) {
                foreach (['UPDATE', 'DELETE'] as $op) {
                    $nome = "trg_{$tabela}_no_".strtolower($op);
                    DB::unprepared("DROP TRIGGER IF EXISTS {$nome}");
                    DB::unprepared("CREATE TRIGGER {$nome} BEFORE {$op} ON {$tabela} FOR EACH ROW
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$tabela} é append-only. Correção é lançamento novo.'");
                }
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            foreach (['pcp_apontamentos', 'pcp_eventos'] as $tabela) {
                DB::unprepared("DROP TRIGGER IF EXISTS trg_{$tabela}_no_update");
                DB::unprepared("DROP TRIGGER IF EXISTS trg_{$tabela}_no_delete");
            }
        }
        foreach (['pcp_eventos', 'pcp_apontamentos', 'pcp_programacoes', 'pcp_operacoes', 'pcp_postos', 'pcp_turnos'] as $tabela) {
            Schema::dropIfExists($tabela);
        }
    }
};
