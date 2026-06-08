<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gerar Boleto no drawer do título (Financeiro/Unificado) — 2026-06-08.
 *
 * Expande o enum `origem_type` em `cobrancas` pra aceitar `fin_titulo`.
 *
 * Por quê: quando a cobrança nasce de um TÍTULO que JÁ existe em
 * `fin_titulos` (botão "Gerar boleto" no drawer), precisamos amarrar a
 * cobrança ao título de origem pra que o webhook de pagamento dê BAIXA
 * nesse título — em vez de criar um título novo (`PG-xxx`), o que contaria
 * o recebível EM DOBRO. O listener `OnCobrancaPagaCreateFinanceiroTitulo`
 * lê `cobranca.origem_type === 'fin_titulo'` pra ramificar a reconciliação.
 *
 * Compatível MySQL/MariaDB (ALTER ENUM via raw SQL) + SQLite (no-op em test —
 * SQLite trata enum como TEXT sem CHECK constraint).
 *
 * Tier 0: schema-only, sem PII. Não toca dados.
 */
return new class extends Migration
{
    /** Valores ANTES desta migration (create_cobrancas_table). */
    private const ENUM_LEGACY = ['sale', 'invoice', 'subscription_license', 'avulsa'];

    /** Valor ADICIONADO por esta migration. */
    private const ENUM_NOVOS = ['fin_titulo'];

    public function up(): void
    {
        if (! Schema::hasTable('cobrancas')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $all = array_merge(self::ENUM_LEGACY, self::ENUM_NOVOS);
            $enumList = "'" . implode("','", $all) . "'";
            DB::statement("ALTER TABLE cobrancas MODIFY COLUMN origem_type ENUM({$enumList}) NULL");
        }
        // SQLite/PgSQL: enum aceito como TEXT; nada a fazer.
    }

    public function down(): void
    {
        if (! Schema::hasTable('cobrancas')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            // Soft-revert: só remove se nenhuma cobrança usa o novo valor.
            $usados = DB::table('cobrancas')
                ->whereIn('origem_type', self::ENUM_NOVOS)
                ->count();
            if ($usados > 0) {
                throw new \RuntimeException(
                    "Não é possível reverter: {$usados} cobranças usam origem_type='fin_titulo'. " .
                    'Migre-as primeiro pra um valor legacy.'
                );
            }
            $enumList = "'" . implode("','", self::ENUM_LEGACY) . "'";
            DB::statement("ALTER TABLE cobrancas MODIFY COLUMN origem_type ENUM({$enumList}) NULL");
        }
    }
};
