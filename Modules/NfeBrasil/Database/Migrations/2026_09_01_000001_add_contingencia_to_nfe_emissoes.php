<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * US-NFE-006 / ADR TECH-0002 (NfeBrasil) — colunas de contingência na emissão.
 *
 * `tp_emis` NÃO EXISTIA (varredura contada: 0 ocorrências em Modules/ e database/
 * antes deste PR). O NfeService gravava tpEmis fixo em 1 direto no XML, sem persistir
 * o modo — então não havia como saber, depois, se a nota saiu normal ou em contingência.
 * A ADR TECH-0002 assume a coluna no exemplo do EmitirNfceJob mas não a lista no DDL.
 *
 * Valores de tp_emis (Manual SEFAZ):
 *   1 = Normal (default — preserva 100% do comportamento atual)
 *   4 = EPEC   (contingência NF-e modelo 55, autoriza em SVC-AN)
 *   9 = Contingência off-line NFC-e (modelo 65)
 * ⚠️ O rótulo do 9 diverge entre docs canon (SPEC diz "FS-DA", gold-set da Jana diz
 * "off-line NFC-e"). Aqui gravamos só o NÚMERO; o rótulo fica fora do schema até a
 * tabela oficial da SEFAZ arbitrar. Não inventar o par aqui.
 *
 * O valor 'contingencia' entra no ENUM de status NO FIM da lista de propósito:
 * MySQL guarda ENUM por ORDINAL, então inserir no meio reescreveria os ordinais dos
 * valores seguintes em toda a tabela. No fim, é ALTER barato e sem reescrita de dado.
 *
 * Idempotente nos dois trechos. down() preserva colunas (append-only ADR 0093 G8) e
 * recusa reverter o ENUM se houver linha em contingência — mesmo padrão da migration
 * 2026_05_10_120000 (enviando/erro_envio), pra não transformar nota viva em dado inválido.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nfe_emissoes')) {
            return;
        }

        Schema::table('nfe_emissoes', function (Blueprint $table) {
            if (! Schema::hasColumn('nfe_emissoes', 'tp_emis')) {
                $table->unsignedTinyInteger('tp_emis')
                    ->default(1)
                    ->comment('SEFAZ tpEmis: 1=normal | 4=EPEC (NF-e 55) | 9=off-line (NFC-e 65). Default 1 preserva emissão legada.');
            }

            if (! Schema::hasColumn('nfe_emissoes', 'retry_count')) {
                $table->unsignedTinyInteger('retry_count')
                    ->default(0)
                    ->comment('Tentativas de retransmissão pós-contingência. ADR TECH-0002: 5 falhas => status=rejeitada + alerta.');
            }

            if (! Schema::hasColumn('nfe_emissoes', 'last_retry_at')) {
                $table->timestamp('last_retry_at')
                    ->nullable()
                    ->comment('Última tentativa de retransmissão. NULL = nunca retentada.');
            }
        });

        $atual = DB::selectOne(
            "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'nfe_emissoes'
               AND COLUMN_NAME = 'status'"
        );

        if ($atual === null || str_contains((string) $atual->COLUMN_TYPE, "'contingencia'")) {
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
                'erro_envio',
                'contingencia'
            ) NOT NULL DEFAULT 'pendente'"
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('nfe_emissoes')) {
            return;
        }

        $emContingencia = DB::table('nfe_emissoes')->where('status', 'contingencia')->count();

        if ($emContingencia > 0) {
            throw new RuntimeException(
                "Cannot rollback: {$emContingencia} emissão(ões) com status 'contingencia'. "
                . 'São notas fiscais reais aguardando transmissão — transmitir (RetentarContingenciaJob) '
                . 'ou reclassificar ANTES de rodar down(). Perder esse estado é problema fiscal.'
            );
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

        // Colunas tp_emis/retry_count/last_retry_at preservadas (append-only ADR 0093 G8).
    }
};
