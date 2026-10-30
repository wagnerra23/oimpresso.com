<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contador do nosso número dos boletos CNAB, por credencial (caso 165 da lista de 30/10/2026).
 *
 * Antes o número saía de `crc32(idempotency_key) % 99999999`, que pode repetir, e o retorno do
 * banco ficava com a primeira cobrança daquele número. Agora cada emissão pega o próximo número
 * desta coluna, com a linha da credencial travada (`CnabBoletoAdapter::proximoNumero`).
 *
 * Schema-only: coluna nova com default 0, não toca dado existente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_gateway_credentials') || Schema::hasColumn('payment_gateway_credentials', 'cnab_ultimo_numero')) {
            return;
        }

        Schema::table('payment_gateway_credentials', function (Blueprint $table) {
            $table->unsignedBigInteger('cnab_ultimo_numero')->default(0);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('payment_gateway_credentials') && Schema::hasColumn('payment_gateway_credentials', 'cnab_ultimo_numero')) {
            Schema::table('payment_gateway_credentials', function (Blueprint $table) {
                $table->dropColumn('cnab_ultimo_numero');
            });
        }
    }
};
