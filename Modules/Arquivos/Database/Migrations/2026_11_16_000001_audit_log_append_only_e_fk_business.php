<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Auditoria Arquivos 2026-11-05, lote L2 (D5).
 *
 * 1. Trigger BEFORE UPDATE em `arquivos_audit_log`: a trilha é append-only (ADR 0123 §8) e até
 *    aqui só o código respeitava isso. Nenhum código nem teste faz UPDATE nela (medido em
 *    2026-11-16). O BEFORE DELETE fica PENDENTE: 6 testes do módulo limpam a trilha por DELETE
 *    e precisam mudar antes (lote L1).
 * 2. FK de `business_id` → `business.id` em `arquivos` e `arquivos_audit_log` (estava comentada).
 *    Medido em produção em 2026-11-16 (leitura): 0 linhas com business_id = 0 e 0 sem empresa
 *    nas duas tabelas; `business.id` é int(10) unsigned, igual às colunas.
 *    Se aparecer linha órfã num ambiente, a FK daquela tabela NÃO entra e fica um Log::warning
 *    com a contagem — não derruba o `migrate --force` do deploy.
 *
 * Idempotente (DROP TRIGGER IF EXISTS; FK só se não existir). Só MySQL: no sqlite não há trigger
 * nem ALTER de FK.
 */
return new class extends Migration
{
    private const TRIGGER_UPDATE = 'trg_arquivos_audit_log_no_update';

    /** tabela => nome da FK */
    private const FKS = [
        'arquivos' => 'fk_arquivos_business',
        'arquivos_audit_log' => 'fk_arquivos_audit_log_business',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS ' . self::TRIGGER_UPDATE);
        DB::unprepared('CREATE TRIGGER ' . self::TRIGGER_UPDATE . " BEFORE UPDATE ON arquivos_audit_log FOR EACH ROW
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'arquivos_audit_log e append-only: grave uma linha nova, nunca altere.'");

        foreach (self::FKS as $tabela => $fk) {
            if ($this->fkExiste($tabela, $fk)) {
                continue;
            }

            $orfas = DB::table($tabela)
                ->leftJoin('business', 'business.id', '=', "{$tabela}.business_id")
                ->whereNull('business.id')
                ->count();
            if ($orfas > 0) {
                Log::warning('arquivos.migration.fk_business_pendente', ['tabela' => $tabela, 'linhas_orfas' => $orfas]);

                continue;
            }

            DB::statement("ALTER TABLE {$tabela} ADD CONSTRAINT {$fk} FOREIGN KEY (business_id) REFERENCES business(id)");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS ' . self::TRIGGER_UPDATE);

        foreach (self::FKS as $tabela => $fk) {
            if ($this->fkExiste($tabela, $fk)) {
                DB::statement("ALTER TABLE {$tabela} DROP FOREIGN KEY {$fk}");
            }
        }
    }

    private function fkExiste(string $tabela, string $fk): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $tabela)
            ->where('CONSTRAINT_NAME', $fk)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }
};
