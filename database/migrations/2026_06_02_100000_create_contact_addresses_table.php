<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-CRM-080 -- tabela satelite `contact_addresses` (1:N enderecos de entrega
 * por contato). Multi-tenant Tier 0 IRREVOGAVEL (ADR 0093): business_id FK +
 * index composto.
 *
 * Vocabulario BR identico a `contacts` (address_line_1/2, numero, neighborhood,
 * city, city_code, state, zip_code, country) pra backfill trivial. Acrescenta
 * label/recipient/phone (especificos de endereco de entrega) + is_default.
 *
 * is_default e NOVO aqui -- NAO confundir com contacts.is_default (legado UPOS,
 * semantica de "contato padrao", nao de endereco). Guard de unicidade
 * (1 default por (business_id, contact_id)) e em app layer (Model + Pest),
 * nao via constraint (permite zero-default transitorio durante reordenacao).
 *
 * Migracao DIRETA (Wagner travou): sem feature flag de coexistencia permanente.
 * A coluna contacts.shipping_address NAO e dropada aqui (rollback safety +
 * accessor compat le dela ate backfill validar em prod).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/sessions/2026-06-02-coord-multiplos-enderecos-entrega.md
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contact_addresses')) {
            return;
        }

        Schema::create('contact_addresses', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Multi-tenant Tier 0 (ADR 0093 Garantia 1).
            $table->unsignedInteger('business_id')->index();
            $table->unsignedBigInteger('contact_id');

            // Identificacao do endereco de entrega.
            $table->string('label', 60)->nullable();        // ex "Matriz", "Obra Centro"
            $table->string('recipient', 120)->nullable();    // destinatario A/C
            $table->string('phone', 30)->nullable();

            // Campos BR -- nomes IDENTICOS a contacts pra backfill trivial.
            $table->string('address_line_1', 255)->nullable();
            $table->string('address_line_2', 255)->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('neighborhood', 120)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('city_code', 7)->nullable();      // IBGE 7 digitos
            $table->string('state', 2)->nullable();          // UF
            $table->string('zip_code', 10)->nullable();
            $table->string('country', 100)->nullable();

            $table->boolean('is_default')->default(false);

            $table->timestamps();
            $table->softDeletes();

            // Index composto multi-tenant (ADR 0093). Nome explicito <=64 chars.
            $table->index(['business_id', 'contact_id'], 'contact_addr_biz_contact_idx');

            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_addresses');
    }
};
