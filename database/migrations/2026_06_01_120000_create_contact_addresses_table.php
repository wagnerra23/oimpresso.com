<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-CRM-078 — múltiplos endereços por contato (matriz/filial/casa/obra) +
 * seletor de endereço de entrega na venda.
 *
 * Tabela ADITIVA `contact_addresses`. NÃO remove os campos de endereço inline
 * de `contacts` (zip_code/address_line_1/numero/.../state) — eles permanecem
 * como o endereço "principal" (compat UltimatePOS, NFe enderDest, Sells
 * shipping_address pré-fill) e são espelhados no endereço `is_default = true`.
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL):
 *   - business_id NOT NULL + FK + index (Garantia 1).
 *   - Model App\ContactAddress usa HasBusinessScope (Garantia 2).
 *
 * Backfill idempotente delegado a App\ContactAddress::backfillInline() — copia
 * o endereço inline de cada contact que tenha QUALQUER campo preenchido para 1
 * `contact_addresses` (is_default = true, is_shipping = true, label "Principal"),
 * preservando business_id do próprio contact. Re-rodável sem duplicar.
 *
 * @see app/ContactAddress.php
 * @see memory/requisitos/Cliente/SPEC.md §US-CRM-078
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contact_addresses')) {
            Schema::create('contact_addresses', function (Blueprint $table) {
                $table->bigIncrements('id');

                // Tier 0 (ADR 0093 §Garantia 1) — business_id NOT NULL + FK + index.
                // unsignedInteger casa com business.id / contacts.id (UPOS = INT UNSIGNED).
                $table->unsignedInteger('business_id')->index();
                $table->unsignedInteger('contact_id')->index();

                // Rótulo livre do endereço (Matriz, Filial Centro, Casa, Obra…).
                $table->string('label', 80)->nullable();

                // Endereço completo — espelha as colunas inline de `contacts`.
                $table->string('zip_code', 10)->nullable();
                $table->string('address_line_1', 255)->nullable();
                // `numero` BR aceita "s/n", "km 8", "1578-A" → string.
                $table->string('numero', 20)->nullable();
                $table->string('address_line_2', 255)->nullable();
                $table->string('neighborhood', 120)->nullable();
                $table->string('city', 120)->nullable();
                // `state` (UF) gera 2 chars em writes novos; 191 evita truncar
                // dados legados no backfill (mysql strict mode).
                $table->string('state', 191)->nullable();
                // `city_code` IBGE 7 dígitos (NFe enderDest/cMun).
                $table->string('city_code', 7)->nullable();

                // Flags: 1 endereço default (principal/cobrança) + 1 de entrega.
                $table->boolean('is_default')->default(false);
                $table->boolean('is_shipping')->default(false);

                $table->timestamps();
                $table->softDeletes();

                // FKs — endereço não sobrevive ao contato/business (cascade).
                $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
                $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');

                // Query mais comum: endereços de um contato dentro do tenant.
                $table->index(['business_id', 'contact_id'], 'contact_addresses_biz_contact_idx');
            });
        }

        // Backfill idempotente do endereço inline → 1ª linha default/shipping.
        \App\ContactAddress::backfillInline();
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_addresses');
    }
};
