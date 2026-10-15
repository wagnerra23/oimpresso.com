<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cadastros do PCP (thread 03 + emenda E03 do playbook de Produção, ADR 0431).
 *
 * Postos, máquinas e veículos continuam em `pcp_postos` (thread 02); aqui entram os roteiros
 * por família (UC-PCV-13), os motivos de pausa/refugo por posto (UC-PCV-33), o custo-hora da
 * pessoa e as ferramentas. Toda tabela nasce com `business_id` indexado + FK (ADR 0093).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pcp_postos', 'placa')) {
            Schema::table('pcp_postos', function (Blueprint $t) {
                $t->string('placa', 10)->nullable()->after('veiculo');
            });
        }

        if (! Schema::hasTable('pcp_motivos')) {
            Schema::create('pcp_motivos', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->foreignId('posto_id')->constrained('pcp_postos')->cascadeOnDelete();
                $t->enum('tipo', ['pausa', 'refugo']);
                $t->string('nome', 80);
                $t->unsignedSmallInteger('ordem')->default(0);
                $t->boolean('ativo')->default(true);
                $t->timestamps();
                $t->unique(['posto_id', 'tipo', 'nome'], 'pcp_motivos_posto_tipo_nome_uq');
                $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('pcp_roteiros')) {
            Schema::create('pcp_roteiros', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->string('familia', 80);
                $t->string('nome', 120);
                $t->boolean('ativo')->default(true);
                $t->timestamps();
                $t->unique(['business_id', 'nome'], 'pcp_roteiros_biz_nome_uq');
                $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            });
        }

        // Uma linha por etapa. `bloco` é a posição no roteiro; etapa em sequência tem `trilha` 0,
        // as duas trilhas de um bloco paralelo têm 1 e 2 e carregam o nome da `junta`.
        if (! Schema::hasTable('pcp_roteiro_etapas')) {
            Schema::create('pcp_roteiro_etapas', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->foreignId('roteiro_id')->constrained('pcp_roteiros')->cascadeOnDelete();
                $t->unsignedSmallInteger('bloco');
                $t->unsignedTinyInteger('trilha')->default(0);
                $t->unsignedSmallInteger('ordem')->default(0);
                $t->string('nome', 120);
                $t->foreignId('posto_id')->nullable()->constrained('pcp_postos');
                $t->decimal('horas', 6, 2)->nullable();
                // Etapa opcional: liga pela opção marcada no orçamento ("com laminação").
                $t->string('opcional_por', 80)->nullable();
                $t->string('junta', 120)->nullable();
                $t->timestamps();
                $t->index(['roteiro_id', 'bloco', 'trilha', 'ordem'], 'pcp_rot_etapas_ordem_idx');
                $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            });
        }

        // Custo-hora da pessoa (E03). Só aparece para quem tem `producao.desempenho.ver`.
        if (! Schema::hasTable('pcp_colaborador_custos')) {
            Schema::create('pcp_colaborador_custos', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->unsignedInteger('user_id');
                $t->decimal('custo_hora', 12, 2);
                $t->timestamps();
                $t->unique(['business_id', 'user_id'], 'pcp_colab_custo_biz_user_uq');
                $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
                $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('pcp_ferramentas')) {
            Schema::create('pcp_ferramentas', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->string('codigo_qr', 40);
                $t->string('nome', 120);
                $t->boolean('ativo')->default(true);
                $t->timestamps();
                $t->unique(['business_id', 'codigo_qr'], 'pcp_ferramentas_biz_qr_uq');
                $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            });
        }

        // A FK do motivo da pausa ficou para esta thread (02, comentário em pcp_apontamentos).
        // Só no MySQL: no sqlite o ALTER recria a tabela, e o apontamento não roda lá.
        if (DB::getDriverName() === 'mysql' && ! $this->temFk('pcp_apontamentos', 'pcp_apont_motivo_fk')) {
            Schema::table('pcp_apontamentos', function (Blueprint $t) {
                $t->foreign('motivo_id', 'pcp_apont_motivo_fk')->references('id')->on('pcp_motivos');
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql' && $this->temFk('pcp_apontamentos', 'pcp_apont_motivo_fk')) {
            Schema::table('pcp_apontamentos', fn (Blueprint $t) => $t->dropForeign('pcp_apont_motivo_fk'));
        }
        foreach (['pcp_ferramentas', 'pcp_colaborador_custos', 'pcp_roteiro_etapas', 'pcp_roteiros', 'pcp_motivos'] as $tabela) {
            Schema::dropIfExists($tabela);
        }
        if (Schema::hasColumn('pcp_postos', 'placa')) {
            Schema::table('pcp_postos', fn (Blueprint $t) => $t->dropColumn('placa'));
        }
    }

    private function temFk(string $tabela, string $nome): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $tabela)
            ->where('CONSTRAINT_NAME', $nome)
            ->exists();
    }
};
