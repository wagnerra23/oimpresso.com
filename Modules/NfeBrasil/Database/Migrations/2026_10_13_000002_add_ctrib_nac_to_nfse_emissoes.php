<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-NFSE-006 — código de tributação nacional (cTribNac) gravado na emissão da NFS-e.
 *
 * 6 dígitos: item (2) + subitem (2) da LC 116/2003 + desdobro nacional (2), resolvido
 * pela tabela do Anexo B oficial (`ListaServicoNacional`). Null nas notas anteriores,
 * que iam com desdobro "01" fixo. A tabela já tem `business_id` indexado (ADR 0093).
 *
 * Idempotente (Schema::hasColumn) + down() preserva a coluna (append-only, ADR 0093 G8).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nfse_emissoes') || Schema::hasColumn('nfse_emissoes', 'ctrib_nac')) {
            return;
        }

        Schema::table('nfse_emissoes', function (Blueprint $table) {
            $table->string('ctrib_nac', 6)->nullable()
                ->comment('Código de tributação nacional: item + subitem LC 116 + desdobro (Anexo B)');
        });
    }

    public function down(): void
    {
        // Append-only (ADR 0093 Garantia 8): coluna preservada — null é inerte.
    }
};
