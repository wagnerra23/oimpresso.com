<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `contacts.mensagem_venda` — texto livre exibido como ALERTA ao vendedor no
 * POS/venda quando o cliente é selecionado. Absorve `PESSOAS.MENSAGEM_PARA_VENDA`
 * do ERP Delphi WR Comercial (ex.: Martinho biz=164 FAN COM tem 398 chars).
 *
 * O dado já foi migrado para esta coluna em produção (biz=164) pelo importer
 * Python; esta migration torna o schema reproduzível em CI e novos ambientes.
 *
 * Editável no drawer 760 Cliente (aba Comercial, autosave on blur via
 * ClienteAutosaveController::comercial) e lido pelo select2 do customer no POS
 * (ContactController::getCustomers → public/js/pos.js).
 *
 * IDEMPOTENTE — Schema::hasColumn check antes de add. Reversível via down()
 * sem perda (coluna nullable, ninguém depende dela pré-existente fora do POS).
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL): `business_id` já existe em
 * `contacts` + indexado — coluna texto herda scope automático.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            if (! Schema::hasColumn('contacts', 'mensagem_venda')) {
                $table->text('mensagem_venda')->nullable();
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
