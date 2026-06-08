<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onda 2 — ADR 0170.
 *
 * Tabela canônica de credenciais de gateway. Substitui (eventualmente)
 * `rb_boleto_credentials` + colunas legacy `accounts.gateway_*`.
 *
 * Backfill dessas duas origens NÃO acontece neste PR (Onda 2.5 separa).
 * Esta migration apenas cria a tabela NOVA — não toca legado.
 *
 * Multi-tenant Tier 0: business_id NOT NULL + index + FK pra business.
 * config_json contém segredos cifrados via Crypt::encryptString (PCI/LGPD).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_credentials', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->enum('gateway_key', ['inter', 'c6', 'asaas', 'bcb_pix', 'pesapal'])->index();
            $table->enum('ambiente', ['production', 'sandbox'])->default('production');
            $table->boolean('ativo')->default(true)->index();
            $table->string('nome_display')->nullable();
            $table->json('config_json'); // segredos cifrados via Crypt::encryptString
            $table->unsignedInteger('conta_bancaria_id')->nullable()->index(); // FK accounts (mantida nullable pra compat até Onda 4)
            $table->enum('health_status', ['ok', 'degraded', 'down', 'unknown'])->default('unknown')->index();
            $table->timestamp('health_checked_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['business_id', 'gateway_key', 'ambiente'],
                'pg_cred_biz_gw_amb_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_credentials');
    }
};
