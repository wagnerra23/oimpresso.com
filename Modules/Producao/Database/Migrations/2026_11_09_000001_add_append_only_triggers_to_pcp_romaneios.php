<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Romaneio gravado não muda (UC-PCROM-05 · US-PCP-023). O Model já recusa UPDATE e DELETE por evento,
 * mas `DB::table(...)` passa por fora dele: o trigger fecha esse caminho, como em `pcp_apontamentos`.
 *
 * Só MySQL. Idempotente: o nome do trigger é derrubado antes de criar.
 */
return new class extends Migration
{
    private const TABELAS = ['pcp_romaneios', 'pcp_romaneio_linhas'];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        foreach (self::TABELAS as $tabela) {
            if (! Schema::hasTable($tabela)) {
                continue;
            }
            foreach (['UPDATE', 'DELETE'] as $op) {
                $nome = "trg_{$tabela}_no_".strtolower($op);
                DB::unprepared("DROP TRIGGER IF EXISTS {$nome}");
                DB::unprepared("CREATE TRIGGER {$nome} BEFORE {$op} ON {$tabela} FOR EACH ROW
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$tabela} é append-only. Gere nova versão do romaneio.'");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        foreach (self::TABELAS as $tabela) {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_{$tabela}_no_update");
            DB::unprepared("DROP TRIGGER IF EXISTS trg_{$tabela}_no_delete");
        }
    }
};
