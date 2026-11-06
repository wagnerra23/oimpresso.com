<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arquivo de linhas do sistema legado (Delphi WR Comercial / OfficeImpresso) que não têm
 * tabela própria no oimpresso: log de auditoria (HISTORICO), log técnico (LOG_SISTEMA),
 * os *_HISTORICO e o que mais for decidido arquivar em vez de importar.
 *
 * Decisão [F] 2026-10-30 na migração da Sabor Brasil: "não se faz migração parcial" — log e
 * histórico são preservados, só leitura. A tabela é genérica (vale para os próximos clientes
 * legados) e mora no núcleo, não em Modules/Officeimpresso, porque a ponte legada vai ser
 * descomissionada (SPEC do Officeimpresso) e o arquivo tem de sobreviver a ela.
 *
 * - Uma linha = uma linha da tabela de origem, inteira, em `payload` (JSON).
 * - `row_hash` (sha1 do payload canônico) dá idempotência: re-importar a mesma linha não
 *   duplica. Precisa existir porque HISTORICO não tem chave primária única.
 * - `ref_table` + `ref_key` ligam a linha ao registro de negócio que ela descreve
 *   (ex.: HISTORICO.TABELA = 'VENDA', CODIGO = 103917), pra consultar o histórico de
 *   uma venda migrada.
 * - `legacy_empresa` guarda o CODEMPRESA da origem; o mapa para business_location sai
 *   do passo que cria as unidades.
 * - Sem `updated_at`: arquivo é append-only.
 *
 * Multi-tenant Tier 0 (ADR 0093): `business_id` obrigatório, indexado e com FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('legacy_archive_rows')) {
            return;
        }

        Schema::create('legacy_archive_rows', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedInteger('business_id');
            $t->string('legacy_source', 50); // wr-comercial-delphi, ...
            $t->string('source_table', 64);
            $t->string('source_pk', 100)->nullable();
            $t->string('legacy_empresa', 20)->nullable();
            $t->string('ref_table', 64)->nullable();
            $t->string('ref_key', 100)->nullable();
            $t->dateTime('occurred_at')->nullable();
            $t->json('payload');
            $t->char('row_hash', 40);
            $t->string('importer_version', 40)->nullable();
            $t->timestamp('imported_at')->useCurrent();

            $t->unique(['business_id', 'legacy_source', 'source_table', 'row_hash'], 'legacy_archive_rows_idem_uq');
            $t->index(['business_id', 'source_table', 'occurred_at'], 'legacy_archive_rows_biz_tab_when_idx');
            $t->index(['business_id', 'ref_table', 'ref_key'], 'legacy_archive_rows_biz_ref_idx');
            $t->foreign('business_id', 'legacy_archive_rows_business_fk')
                ->references('id')->on('business')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_archive_rows');
    }
};
