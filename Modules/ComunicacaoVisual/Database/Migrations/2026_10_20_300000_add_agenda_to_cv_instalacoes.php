<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agenda da instalação (US-COMVIS-007 · UC-FLX-21).
 *
 * `cv_instalacoes` nasceu (2026-05-12) presa a `cv_ordens_producao` por `ordem_id` NOT NULL, mas
 * a OS viva da CV é `comvis_os` (aprovação do orçamento, apontamentos, etapa `instalacao`) e
 * nenhum código grava em `cv_ordens_producao`. Esta migration liga a instalação à OS viva:
 *   - `os_id`    FK comvis_os (nullable; índice business_id + os_id)
 *   - `data_fim` fim da janela; `data_agendada` é o início
 *   - `veiculo`  texto curto (placa/apelido) — não há cadastro de frota da empresa
 *   - `ordem_id` passa a nullable (a instalação agendada pela OS não tem ordem de produção)
 *
 * Idempotente (hasColumn / hasIndex / getForeignKeys). Em SQLite pula as FKs.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('cv_instalacoes')) {
            return;
        }
        $mysql = DB::getDriverName() !== 'sqlite';
        $fks = $mysql ? array_column(Schema::getForeignKeys('cv_instalacoes'), 'name') : [];

        // MySQL não muda a nulidade de coluna presa em FK: solta, muda e prende de novo.
        if (in_array('fk_cv_inst_ordem', $fks, true)) {
            Schema::table('cv_instalacoes', fn (Blueprint $t) => $t->dropForeign('fk_cv_inst_ordem'));
        }
        Schema::table('cv_instalacoes', fn (Blueprint $t) => $t->unsignedBigInteger('ordem_id')->nullable()->change());
        if ($mysql) {
            Schema::table('cv_instalacoes', fn (Blueprint $t) => $t->foreign('ordem_id', 'fk_cv_inst_ordem')
                ->references('id')->on('cv_ordens_producao')->cascadeOnDelete());
        }

        Schema::table('cv_instalacoes', function (Blueprint $t) {
            if (! Schema::hasColumn('cv_instalacoes', 'os_id')) {
                $t->unsignedBigInteger('os_id')->nullable()->after('ordem_id');
            }
            if (! Schema::hasColumn('cv_instalacoes', 'data_fim')) {
                $t->timestamp('data_fim')->nullable()->after('data_agendada');
            }
            if (! Schema::hasColumn('cv_instalacoes', 'veiculo')) {
                $t->string('veiculo', 60)->nullable()->after('equipe_user_ids_json');
            }
        });

        if (! Schema::hasIndex('cv_instalacoes', 'idx_cv_inst_business_os')) {
            Schema::table('cv_instalacoes', fn (Blueprint $t) => $t->index(['business_id', 'os_id'], 'idx_cv_inst_business_os'));
        }
        if ($mysql && ! in_array('fk_cv_inst_os', $fks, true)) {
            Schema::table('cv_instalacoes', fn (Blueprint $t) => $t->foreign('os_id', 'fk_cv_inst_os')
                ->references('id')->on('comvis_os')->cascadeOnDelete());
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('cv_instalacoes')) {
            return;
        }
        if (DB::getDriverName() !== 'sqlite'
            && in_array('fk_cv_inst_os', array_column(Schema::getForeignKeys('cv_instalacoes'), 'name'), true)) {
            Schema::table('cv_instalacoes', fn (Blueprint $t) => $t->dropForeign('fk_cv_inst_os'));
        }
        if (Schema::hasIndex('cv_instalacoes', 'idx_cv_inst_business_os')) {
            Schema::table('cv_instalacoes', fn (Blueprint $t) => $t->dropIndex('idx_cv_inst_business_os'));
        }
        foreach (['os_id', 'data_fim', 'veiculo'] as $coluna) {
            if (Schema::hasColumn('cv_instalacoes', $coluna)) {
                Schema::table('cv_instalacoes', fn (Blueprint $t) => $t->dropColumn($coluna));
            }
        }
        // `ordem_id` fica nullable: voltar a NOT NULL quebraria as instalações agendadas pela OS.
    }
};
