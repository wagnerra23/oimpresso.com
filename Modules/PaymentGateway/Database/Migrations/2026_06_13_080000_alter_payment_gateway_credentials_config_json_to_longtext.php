<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * US-GOV-018 Frente B — `config_json`: json → longtext.
 *
 * A coluna foi criada como `json()` (2026_05_19_120000:30) mas o Model casta
 * `encrypted:array` (PaymentGatewayCredential.php:66 — Crypt::encryptString
 * produz um blob base64, que NÃO é JSON válido). SQLite TEXT aceitava o blob;
 * o MySQL 8 strict rejeita com SQLSTATE[22032] 3140 "Invalid JSON text" — 212
 * falhas no nightly full-suite (run 20260613-003042, sha d14f5436), reproduzidas
 * byte-a-byte no retest adversarial (counterfactual: ALTER … LONGTEXT aceita).
 * O próprio EncryptedCredentialCastTest.php:40 já declara a coluna como `text()`.
 * Esta migration alinha o schema de produção ao que o cast exige.
 *
 * Multi-tenant Tier 0 (business_id) preservado — ALTER COLUMN não toca tenancy.
 * Idempotente: só altera se a coluna ainda for json (no-op em sqlite, onde json
 * já é TEXT por baixo). O blob cifrado já é string, então json→longtext preserva
 * 100% dos dados.
 *
 * @see Modules/PaymentGateway/Models/PaymentGatewayCredential.php:66
 * Refs: US-GOV-018 fase 2b · ADR 0170
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_gateway_credentials')) {
            return;
        }

        $type = $this->configJsonType();
        if ($type !== null && str_contains(strtolower($type), 'json')) {
            DB::statement('ALTER TABLE payment_gateway_credentials MODIFY config_json LONGTEXT NOT NULL');
        }
        // sqlite (CI rápido Unit): json mapeia pra TEXT — nada a fazer.
    }

    public function down(): void
    {
        if (! Schema::hasTable('payment_gateway_credentials')) {
            return;
        }

        $type = $this->configJsonType();
        // Reversão de schema. CAVEAT: se houver linhas com blob cifrado (não-JSON),
        // o MySQL recusará a volta pra JSON (3140) — comportamento correto, pois o
        // dado não é JSON válido. Só roda em mysql com a coluna não-json.
        if (DB::getDriverName() === 'mysql' && $type !== null && ! str_contains(strtolower($type), 'json')) {
            DB::statement('ALTER TABLE payment_gateway_credentials MODIFY config_json JSON NOT NULL');
        }
    }

    /**
     * DATA_TYPE atual de config_json (null em não-mysql → up/down viram no-op).
     */
    private function configJsonType(): ?string
    {
        if (DB::getDriverName() !== 'mysql') {
            return null;
        }

        $col = DB::selectOne(
            'SELECT DATA_TYPE FROM information_schema.COLUMNS '
            .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['payment_gateway_credentials', 'config_json']
        );

        return $col->DATA_TYPE ?? null;
    }
};
