<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bridge table — mapeia accounts (core UltimatePOS) → origem legacy externa.
 *
 * Decisão: ADR 0118 (segregação dominios externos) + proibições.md
 * (não modificar tabelas core UltimatePOS sem bridge table).
 *
 * Permite UPSERT idempotente de imports legacy via UNIQUE
 * (business_id, legacy_source, legacy_id) sem mexer no schema core.
 *
 * Multi-tenant Tier 0 (ADR 0093) — business_id global scope obrigatório.
 *
 * Casos de uso atuais:
 *  - Migração one-shot Delphi WR Comercial CONTAS → accounts (Fase 5+)
 *  - Futuro: Bling/Tiny/Sankhya (qualquer ERP externo importado)
 *
 * Operação superadmin only — UI futura ficará em Modules/MigrationFactory.
 * Os DADOS são tenant-scoped (business_id obrigatório); o USO da migration
 * factory é restrito a superadmins (Wagner) via can('superadmin').
 */
class CreateAccountsLegacyMapTable extends Migration
{
    public function up(): void
    {
        Schema::create('accounts_legacy_map', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('business_id')->unsigned()->index();
            $table->integer('account_id')->unsigned()
                  ->comment('FK accounts.id (core UltimatePOS)');

            $table->string('legacy_source', 50)
                  ->comment('Identifica sistema origem: wr-comercial-delphi, bling, tiny, sankhya, etc');
            $table->string('legacy_id', 100)
                  ->comment('PK original no sistema legacy (CODIGO Delphi, ID Bling, etc) — string pra acomodar tipos diversos');

            $table->timestamp('legacy_imported_at')->useCurrent();
            $table->string('legacy_importer_version', 20)->nullable()
                  ->comment('Ex: import-contas-bancarias-py-0.1.0 — pra rastrear qual versão importou');
            $table->json('legacy_metadata')->nullable()
                  ->comment('Snapshot do registro original em JSON pra audit/debug');

            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');

            // Idempotência: 1 registro legacy = 1 mapping per tenant
            $table->unique(['business_id', 'legacy_source', 'legacy_id'], 'uq_biz_source_legacy');

            // Lookup reverso eficiente (operações superadmin filtrando por origem)
            $table->index(['legacy_source', 'business_id'], 'idx_source_biz');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts_legacy_map');
    }
}
