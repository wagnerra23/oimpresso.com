<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-FIN-046 — Sicoob mTLS reusa NfeCertificado (single source of truth).
 *
 * Drops colunas adicionadas pelo PR1 da US-FIN-044 (2026_05_27_120000_add_sicoob_api_*)
 * que ficaram redundantes após decisão Wagner 2026-05-27 de reusar
 * NfeCertificado em vez de upload duplicado:
 *
 *   - requires_mtls (bool) — sempre true pra sicoob_api (driver decide)
 *   - mtls_pfx_path (string) — desnecessário, driver lê do NfeCertificado canon
 *
 * Mantém:
 *   - sicoob_api no enum gateway_key (driver continua)
 *   - config_json (sem mtls_pfx_password_encrypted — agora vem do NfeCertificado)
 *
 * Tier 0: schema-only, sem PII. SQLite no-op se dropColumn falhar
 * (SQLite limitations). MySQL/MariaDB ALTER TABLE DROP COLUMN.
 *
 * Refs: US-FIN-046 Wagner 2026-05-27.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_gateway_credentials')) {
            return;
        }

        Schema::table('payment_gateway_credentials', function (Blueprint $table) {
            if (Schema::hasColumn('payment_gateway_credentials', 'mtls_pfx_path')) {
                $table->dropColumn('mtls_pfx_path');
            }
            if (Schema::hasColumn('payment_gateway_credentials', 'requires_mtls')) {
                $table->dropColumn('requires_mtls');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payment_gateway_credentials')) {
            return;
        }

        Schema::table('payment_gateway_credentials', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_gateway_credentials', 'requires_mtls')) {
                $table->boolean('requires_mtls')->default(false)->after('ambiente');
            }
            if (! Schema::hasColumn('payment_gateway_credentials', 'mtls_pfx_path')) {
                $table->string('mtls_pfx_path', 255)->nullable()->after('requires_mtls');
            }
        });
    }
};
