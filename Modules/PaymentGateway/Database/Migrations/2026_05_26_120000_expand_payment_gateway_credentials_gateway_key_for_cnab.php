<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Onda 4f.0 — ADR 0170 (drivers separados top-5 bancos).
 *
 * Expande o enum `gateway_key` em `payment_gateway_credentials` pra aceitar:
 *
 *   CNAB (Onda 4f.cnab — 5 drivers paralelos):
 *     - bradesco_cnab · itau_cnab · bb_cnab · santander_cnab · caixa_cnab
 *
 *   REST API (Ondas 4f-4j — sequencial):
 *     - bradesco_api · itau_api · bb_api · santander_api
 *     (caixa_api NÃO entra — portal Caixa inviável hoje, reavaliar Q3-2026)
 *
 * Compatível MySQL (ALTER ENUM via raw SQL) + SQLite (no-op em test —
 * SQLite trata enum como TEXT sem CHECK constraint).
 *
 * Tier 0: schema-only, sem PII. Não toca dados.
 *
 * Refs: ADR 0170-bancos-nativos-top5-drivers-separados — Onda 4f.0
 */
return new class extends Migration
{
    /** Valores ANTES desta migration. */
    private const ENUM_LEGACY = [
        'inter', 'c6', 'asaas', 'bcb_pix', 'pesapal', 'pagarme',
    ];

    /** Valores ADICIONADOS por esta migration. */
    private const ENUM_NOVOS = [
        // CNAB (Onda 4f.cnab)
        'bradesco_cnab', 'itau_cnab', 'bb_cnab', 'santander_cnab', 'caixa_cnab',
        // REST API (Ondas 4f-4j)
        'bradesco_api', 'itau_api', 'bb_api', 'santander_api',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('payment_gateway_credentials')) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        $all = array_merge(self::ENUM_LEGACY, self::ENUM_NOVOS);

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $enumList = "'" . implode("','", $all) . "'";
            DB::statement("ALTER TABLE payment_gateway_credentials MODIFY COLUMN gateway_key ENUM({$enumList}) NOT NULL");
        }
        // SQLite/PgSQL: enum aceito como TEXT; nada a fazer.
    }

    public function down(): void
    {
        if (! Schema::hasTable('payment_gateway_credentials')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $enumList = "'" . implode("','", self::ENUM_LEGACY) . "'";
            // Soft-revert: só remove se nenhuma credencial usa os novos valores.
            $usados = DB::table('payment_gateway_credentials')
                ->whereIn('gateway_key', self::ENUM_NOVOS)
                ->count();
            if ($usados > 0) {
                throw new \RuntimeException(
                    "Não é possível reverter: {$usados} credenciais usam gateway_key CNAB/API. " .
                    'Migre-as primeiro pra um valor legacy.'
                );
            }
            DB::statement("ALTER TABLE payment_gateway_credentials MODIFY COLUMN gateway_key ENUM({$enumList}) NOT NULL");
        }
    }
};
