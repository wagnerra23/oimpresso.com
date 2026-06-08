<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0197 (Bucket A complemento) — coluna dedicada `mensagem_venda`.
 *
 * Origem: PESSOAS.MENSAGEM_PARA_VENDA (WR Comercial Delphi). Campo DISTINTO de
 * OBSERVACAO (->obs_comercial): a "Mensagem para a Venda" é o alerta exibido ao
 * vendedor no momento da venda (ex.: "cliente tem haver de R$ X", restrições),
 * enquanto OBSERVACAO é nota cadastral geral. Confirmado por Wagner 2026-05-28.
 *
 * Por que TEXT e não custom_field: enrich-contacts-v2 colocou a mensagem em
 * custom_field1 (varchar 191), TRUNCANDO 16 registros (máx real 522 chars) e
 * sobrecarregando o campo. TEXT preserva o conteúdo completo sem truncar.
 *
 * IDEMPOTENTE — Schema::hasColumn antes do add (col já aplicada em prod biz=164
 * via script de migração; aqui registra o schema p/ demais ambientes e evita
 * drift). Reversível via down().
 *
 * @see scripts/legacy-migration/enrich-mensagem-venda.py
 * @see memory/decisions/0197-extend-contacts-absorcao-pessoas-legacy.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            if (! Schema::hasColumn('contacts', 'mensagem_venda')) {
                $table->text('mensagem_venda')->nullable()->after('obs_comercial');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            if (Schema::hasColumn('contacts', 'mensagem_venda')) {
                $table->dropColumn('mensagem_venda');
            }
        });
    }
};
