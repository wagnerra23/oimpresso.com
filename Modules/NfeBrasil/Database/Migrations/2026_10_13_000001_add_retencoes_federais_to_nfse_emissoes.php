<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-NFSE-003 — retenções federais PIS/COFINS/CSLL gravadas na emissão da NFS-e.
 *
 * `retencao_federal_sujeito` é a marcação de quem emite ("serviço sujeito à retenção
 * federal", Lei 10.833/2003 art. 30 — decisão [W] 2026-10-13). Os três valores são o
 * que o `RetencoesNfseService` calculou na hora da emissão; default 0 = nada retido,
 * que é o estado de toda nota anterior a esta migração.
 *
 * Mora no NfeBrasil por D-NFSE-CONSOLIDA ([W] 2026-10-13). A tabela já tem
 * `business_id` indexado (ADR 0093).
 *
 * Idempotente (Schema::hasColumn) + down() preserva as colunas (append-only, ADR 0093 G8).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nfse_emissoes')) {
            return;
        }

        Schema::table('nfse_emissoes', function (Blueprint $table) {
            if (! Schema::hasColumn('nfse_emissoes', 'retencao_federal_sujeito')) {
                $table->boolean('retencao_federal_sujeito')->default(false)
                    ->comment('Quem emitiu marcou: serviço do art. 30 da Lei 10.833/2003');
            }
            if (! Schema::hasColumn('nfse_emissoes', 'valor_ret_pis')) {
                $table->decimal('valor_ret_pis', 15, 2)->default(0);
            }
            if (! Schema::hasColumn('nfse_emissoes', 'valor_ret_cofins')) {
                $table->decimal('valor_ret_cofins', 15, 2)->default(0);
            }
            if (! Schema::hasColumn('nfse_emissoes', 'valor_ret_csll')) {
                $table->decimal('valor_ret_csll', 15, 2)->default(0);
            }
        });
    }

    public function down(): void
    {
        // Append-only (ADR 0093 Garantia 8): colunas preservadas — default 0 é inerte.
    }
};
