<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Envio automático do DANFE por empresa (lote 2 dos débitos do Fiscal, 2026-11-19).
 *
 * Até aqui as duas chaves eram só de deploy (`nfebrasil.email_danfe_on_autorizada` e
 * `nfebrasil.email_danfe_nfce_on_autorizada`). Agora cada empresa pode ligar ou desligar
 * o seu envio em /fiscal/config. Nulo = segue a chave do servidor, que é o comportamento
 * de antes para todas as empresas existentes.
 *
 * Idempotente (Schema::hasColumn) + down() preserva as colunas (append-only, ADR 0093 G8).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nfe_business_configs')) {
            return;
        }

        Schema::table('nfe_business_configs', function (Blueprint $table) {
            if (! Schema::hasColumn('nfe_business_configs', 'email_danfe_nfe')) {
                $table->boolean('email_danfe_nfe')
                    ->nullable()
                    ->comment('Envio do DANFE da NF-e (55) ao autorizar. Nulo = segue nfebrasil.email_danfe_on_autorizada.');
            }
            if (! Schema::hasColumn('nfe_business_configs', 'email_danfe_nfce')) {
                $table->boolean('email_danfe_nfce')
                    ->nullable()
                    ->comment('Envio do DANFE da NFC-e (65) ao autorizar. Nulo = segue nfebrasil.email_danfe_nfce_on_autorizada.');
            }
        });
    }

    public function down(): void
    {
        // Append-only (ADR 0093 Garantia 8): colunas preservadas no rollback.
    }
};
