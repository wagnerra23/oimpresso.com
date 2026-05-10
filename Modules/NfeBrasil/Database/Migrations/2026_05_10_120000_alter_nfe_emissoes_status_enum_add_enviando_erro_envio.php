<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona valores 'enviando' e 'erro_envio' ao ENUM nfe_emissoes.status.
 *
 * Pre-requisito do PR #434 (NfeService::emitir refactor 3-fase): SEFAZ HTTP
 * sai de dentro de DB::transaction. Novo flow:
 *   TX1 curta:    status=enviando + numero locked + INSERT NfeEmissao
 *   FORA tx:      signNFe + sefazEnviaLote (try/catch -> status=erro_envio)
 *   TX2 curta:    processarRetorno -> status=autorizada|rejeitada|...
 *
 * Schema atual em prod (verificado SHOW CREATE TABLE em 2026-05-10):
 *   ENUM('pendente','autorizada','rejeitada','cancelada','denegada','inutilizada')
 *
 * Schema novo:
 *   ENUM('pendente','enviando','autorizada','rejeitada','cancelada','denegada','inutilizada','erro_envio')
 *
 * Idempotente: DESCRIBE checa valores atuais antes de ALTER. Rerun safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nfe_emissoes')) {
            return;
        }

        $current = DB::selectOne(
            "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'nfe_emissoes'
               AND COLUMN_NAME = 'status'"
        );

        if ($current === null) {
            return;
        }

        if (str_contains((string) $current->COLUMN_TYPE, "'enviando'")
            && str_contains((string) $current->COLUMN_TYPE, "'erro_envio'")) {
            return;
        }

        DB::statement(
            "ALTER TABLE nfe_emissoes MODIFY COLUMN status ENUM(
                'pendente',
                'enviando',
                'autorizada',
                'rejeitada',
                'cancelada',
                'denegada',
                'inutilizada',
                'erro_envio'
            ) NOT NULL DEFAULT 'pendente'"
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('nfe_emissoes')) {
            return;
        }

        $rowsComStatusNovo = DB::table('nfe_emissoes')
            ->whereIn('status', ['enviando', 'erro_envio'])
            ->count();

        if ($rowsComStatusNovo > 0) {
            throw new RuntimeException(
                "Cannot rollback: {$rowsComStatusNovo} row(s) em nfe_emissoes com status 'enviando' ou 'erro_envio'. "
                . "Migrar pra 'pendente' ou 'rejeitada' antes de rodar down()."
            );
        }

        DB::statement(
            "ALTER TABLE nfe_emissoes MODIFY COLUMN status ENUM(
                'pendente',
                'autorizada',
                'rejeitada',
                'cancelada',
                'denegada',
                'inutilizada'
            ) NOT NULL DEFAULT 'pendente'"
        );
    }
};
