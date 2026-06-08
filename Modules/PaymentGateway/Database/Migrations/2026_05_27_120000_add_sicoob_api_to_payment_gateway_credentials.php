<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Onda 4f.sicoob_api — US-FIN-044.
 *
 * Adiciona suporte ao driver REST API do Sicoob no enum gateway_key,
 * mais 2 colunas necessárias pra credenciais que exigem certificado
 * mTLS (Sicoob/BB/Bradesco usam .pfx + senha — mantemos coluna agnóstica
 * pra ser reusada por API drivers futuros).
 *
 *   - sicoob_api (REST v3 com OAuth2 + mTLS)
 *   - requires_mtls (boolean) — flag declarativa do driver
 *   - mtls_pfx_path (string) — path por tenant em storage/app/private
 *
 * O .pfx em si NÃO entra no DB — fica em
 * storage/app/private/sicoob/{business_id}.pfx; aqui só o ponteiro.
 * Senha do .pfx vive em config_json (cifrada via Crypt::encryptString),
 * NUNCA em log/audit.
 *
 * Tier 0: schema-only. Não toca dados. SQLite no-op (TEXT sem CHECK).
 *
 * Refs: US-FIN-044 — Wagner aprovou 2026-05-27 (sessão "kamila quer
 *       cadastrar o cobrança pelo sicoob"). ADR 0170 §4f.sicoob_api.
 */
return new class extends Migration
{
    /**
     * Enum legacy ANTES desta migration (pós-Onda 4f.cnab).
     *
     * @var array<int, string>
     */
    private const ENUM_LEGACY = [
        'inter', 'c6', 'asaas', 'bcb_pix', 'pesapal', 'pagarme',
        'bradesco_cnab', 'itau_cnab', 'bb_cnab', 'santander_cnab', 'caixa_cnab',
        'sicoob_cnab', 'ailos_cnab', 'sicredi_cnab', 'cresol_cnab', 'banrisul_cnab', 'btg_cnab',
        'bradesco_api', 'itau_api', 'bb_api', 'santander_api',
    ];

    private const ENUM_NOVOS = ['sicoob_api'];

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

        Schema::table('payment_gateway_credentials', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_gateway_credentials', 'requires_mtls')) {
                $table->boolean('requires_mtls')->default(false)->after('ambiente');
            }
            if (! Schema::hasColumn('payment_gateway_credentials', 'mtls_pfx_path')) {
                $table->string('mtls_pfx_path', 255)->nullable()->after('requires_mtls');
            }
        });
    }

    public function down(): void
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

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $usados = DB::table('payment_gateway_credentials')
                ->whereIn('gateway_key', self::ENUM_NOVOS)
                ->count();

            if ($usados > 0) {
                throw new \RuntimeException(
                    "Não é possível reverter: {$usados} credenciais usam sicoob_api. " .
                    'Migre-as antes pra outro gateway_key.'
                );
            }

            $enumList = "'" . implode("','", self::ENUM_LEGACY) . "'";
            DB::statement("ALTER TABLE payment_gateway_credentials MODIFY COLUMN gateway_key ENUM({$enumList}) NOT NULL");
        }
    }
};
