<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wagner 2026-05-22 -- adiciona `city_code` (codigo IBGE municipio, 7 digitos)
 * em `contacts` pra emissao de documentos fiscais (NFe/NFCe/NFSe exigem
 * codigo IBGE nos campos enderEmit/cMun e enderDest/cMun do XML).
 *
 * BrasilAPI ja retorna `codigo_municipio` no lookup CNPJ (mesma chamada
 * de razao_social/endereco) -- `BrLookupService::lookupCnpj` propaga pro
 * `IdentificacaoTab.handleCnpjLookup` que dispara PATCH /endereco
 * persistindo aqui. EnderecoTab vai expor o campo read-only com prefixo
 * "IBGE:" (Wave futura -- por ora apenas armazena).
 *
 * IDEMPOTENTE -- Schema::hasColumn check. Reversivel: down() dropa coluna
 * apenas se ela existir (preserva campos pre-existentes hipoteticos).
 *
 * Multi-tenant: `contacts.business_id` ja existe + indexado (UPOS core).
 * Esta migration NAO adiciona indice em city_code -- volume de queries
 * por cidade especifica e baixo (relatorio fiscal anual no maximo).
 *
 * LGPD: city_code e dado publico IBGE (nao PII). Nao entra em $logOnly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            // Codigo IBGE do municipio (7 digitos numericos). Permite null
            // pra cadastros legados onde nao temos a informacao.
            //   Ex: 3550308 = Sao Paulo/SP, 3304557 = Rio de Janeiro/RJ
            // Posicionado apos `neighborhood` (ultima coluna endereco da
            // Wave B) pra agrupar campos de endereco fisicamente no schema.
            if (! Schema::hasColumn('contacts', 'city_code')) {
                $table->string('city_code', 7)->nullable()->after('state');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            if (Schema::hasColumn('contacts', 'city_code')) {
                $table->dropColumn('city_code');
            }
        });
    }
};
