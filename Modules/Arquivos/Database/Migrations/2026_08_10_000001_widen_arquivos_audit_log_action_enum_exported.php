<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Amplia o enum `arquivos_audit_log.action` com `exported`.
 *
 * ── O BUG (mesma classe do `signed_url_consumed`, PR #3658) ──────────────────
 * `ExportZipCommand` insere `'action' => 'exported'` (linha ~380) DENTRO de um
 * `try/catch (\Throwable)`. O valor NUNCA existiu no enum: nem no original
 * (2026_05_10_000002 — upload/download/classify/reclassify/soft_delete/restore/
 * hard_delete/signed_url_issued) nem no widen de 2026_07_02, que só acrescentou
 * `signed_url_consumed`.
 *
 * Em MySQL strict mode o INSERT falha, o catch engole, e o comando segue
 * retornando 0. Resultado: `arquivos:export-zip` exporta os arquivos e grava
 * ZERO trilha de auditoria — exatamente o que a [ADR 0123 §8] exige e o que a
 * LGPD Art. 18 (direito de acesso do titular) torna obrigatório registrar.
 *
 * Ficou invisível porque o guarda que provaria isso — `ExportZipCommandTest`,
 * o teste "audit log exported é inserido para cada arquivo (LGPD Art. 18)" — é
 * MySQL-only e a única lane que o alcançava rodava sqlite: dava markTestSkipped,
 * e skip sai exit 0. Só apareceu quando o eixo 2 do test-lane-coverage o ligou.
 *
 * ── ESCOPO MEDIDO, não presumido ─────────────────────────────────────────────
 * Varredura de TODOS os `'action' => '<valor>'` inseridos em código
 * (`Modules/` + `app/`): 9 distintos, dos quais 8 já estão no enum. O único
 * ausente é `exported`. O `skipped` que a varredura devolveu é falso-positivo —
 * vem de `GenerateModuleRequirementsCommand.php:101`, um `$summary[]` sem
 * relação com esta tabela. Por isso o enum ganha UM valor, não dois.
 *
 * Append-only (ADR 0123 §8): esta migration só AMPLIA. O `down()` recusa
 * estreitar se já houver linhas `exported` gravadas — perda de audit real é
 * pior que reversão bloqueada.
 *
 * @see Modules/Arquivos/Console/Commands/ExportZipCommand.php
 * @see Modules/Arquivos/Database/Migrations/2026_07_02_000001_widen_arquivos_audit_log_action_enum.php
 * @see memory/decisions/0123-modules-arquivos-backbone.md §8
 */
return new class extends Migration
{
    private const ENUM_WIDE = "'upload','download','classify','reclassify','soft_delete','restore','hard_delete','signed_url_issued','signed_url_consumed','exported'";

    private const ENUM_NARROW = "'upload','download','classify','reclassify','soft_delete','restore','hard_delete','signed_url_issued','signed_url_consumed'";

    public function up(): void
    {
        // MySQL-only: SQLite (lane de teste reduzida) não tem MODIFY COLUMN e o bug
        // de ENUM strict só existe no MySQL. Mesma convenção do widen de 2026_07_02.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }
        if (! Schema::hasTable('arquivos_audit_log')) {
            return;
        }

        // Idempotente: re-rodar com o mesmo alvo é no-op para o MySQL.
        DB::statement(
            'ALTER TABLE arquivos_audit_log MODIFY COLUMN action ENUM(' . self::ENUM_WIDE . ') NOT NULL'
        );
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }
        if (! Schema::hasTable('arquivos_audit_log')) {
            return;
        }

        // Guarda append-only: estreitar o enum com linhas `exported` gravadas
        // truncaria/invalidaria audit real (LGPD Art. 18). Recusa explícita >
        // perda silenciosa — que é justamente o defeito que esta migration conserta.
        $exported = DB::table('arquivos_audit_log')
            ->where('action', 'exported')
            ->count();

        if ($exported > 0) {
            throw new \RuntimeException(
                "Reversão bloqueada: {$exported} linha(s) 'exported' em arquivos_audit_log. "
                . 'Estreitar o enum orfanaria audit append-only (ADR 0123 §8).'
            );
        }

        DB::statement(
            'ALTER TABLE arquivos_audit_log MODIFY COLUMN action ENUM(' . self::ENUM_NARROW . ') NOT NULL'
        );
    }
};
