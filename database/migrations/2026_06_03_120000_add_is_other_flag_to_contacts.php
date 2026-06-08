<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0246 — Tipo "Outros" como categoria default em migrações legacy.
 *
 * Estende ADR 0188 (flags aditivas multi-type) adicionando a 5ª flag `is_other`
 * pra acomodar registros legacy que não se encaixam nos 4 papéis canônicos
 * (customer/supplier/employee/representative).
 *
 * Caso de uso primário: migração WR Comercial Delphi → oimpresso (Wave 30).
 * 12.233 dos 13.703 cadastros PESSOAS no Firebird WR2 têm TIPO='O' (pré-venda,
 * leads de feira antiga, pessoa genérica, cancelados) que viram tipo "Outros"
 * no oimpresso.
 *
 * Schema:
 *   is_other          TINYINT(1) NOT NULL DEFAULT 0  AFTER is_representative
 *
 * Validação relaxada (server-side em StoreContactRequest/UpdateContactRequest):
 *   - CPF/CNPJ não-obrigatório quando is_other=1
 *   - Conversão Outros→Customer/Supplier/Employee/Representative exige documento
 *     do tipo destino (ContactTypeConversionService valida)
 *
 * IDEMPOTENTE — Schema::hasColumn check protege re-execução.
 * down() reverte sem perda de dados.
 *
 * @see memory/decisions/0246-tipo-outros-default-migracoes-legacy.md
 * @see memory/decisions/0188-contacts-multi-type-flag-aditiva.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            if (! Schema::hasColumn('contacts', 'is_other')) {
                $table->boolean('is_other')->default(false)->after('is_representative');
            }
        });

        // Índice composto Tier 0 multi-tenant (ADR 0093 IRREVOGÁVEL).
        // business_id PRIMEIRO · filtro is_other explora rapidez do índice.
        Schema::table('contacts', function (Blueprint $table) {
            $existing = collect(DB::select('SHOW INDEXES FROM contacts'))
                ->pluck('Key_name')
                ->toArray();

            if (! in_array('idx_contacts_biz_other', $existing, true)) {
                $table->index(['business_id', 'is_other'], 'idx_contacts_biz_other');
            }
        });

        // Backfill intencionalmente VAZIO — sem ADR 0246 ativa antes, nenhum
        // cadastro existente é tipo "other". Default false já cobre.
        // Migration Wave 30 (importer WR2) seta is_other=1 apenas pros legacy
        // que não casam com customer/supplier/employee/representative.
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $existing = collect(DB::select('SHOW INDEXES FROM contacts'))
                ->pluck('Key_name')
                ->toArray();

            if (in_array('idx_contacts_biz_other', $existing, true)) {
                $table->dropIndex('idx_contacts_biz_other');
            }

            if (Schema::hasColumn('contacts', 'is_other')) {
                $table->dropColumn('is_other');
            }
        });
    }
};
