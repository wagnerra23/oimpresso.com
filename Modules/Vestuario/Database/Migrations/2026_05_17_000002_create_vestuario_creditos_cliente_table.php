<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration vestuario_creditos_cliente — Wave 28 G2 W22.
 *
 * Saldo de crédito por cliente (ficha eletrônica) gerado por devoluções
 * tipo `credito_ficha`. Padrão setor "Vale-Trocas" (Bling) / "Troca Fácil"
 * (Linx) — taxa de retenção 60-80% pois cliente vestuário tende a comprar
 * outra peça em breve.
 *
 * CDC Art. 50: garantia contratual de validade — crédito DEVE expirar
 * em prazo razoável (jurisprudência STJ aceita mínimo 90 dias; setor
 * vestuário tipicamente 6 meses).
 *
 * UNIQUE(business_id, contact_id): 1 saldo per cliente per business.
 * Debitar/creditar via UPDATE atomico (Service garante).
 *
 * Multi-tenant Tier 0 ([ADR 0093]):
 * - business_id NOT NULL indexed (composto com contact_id)
 *
 * @see Modules/Vestuario/Services/DevolucaoService.php
 * @see memory/requisitos/Vestuario/CAPTERRA-FICHA.md (W22 G2)
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('vestuario_creditos_cliente')) {
            return;
        }

        Schema::create('vestuario_creditos_cliente', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('contact_id');
            $table->decimal('saldo_credito', 10, 2)->default(0);
            $table->timestamp('expira_em')->nullable(); // CDC Art. 50 — setor: 6 meses
            $table->timestamps();
            $table->softDeletes();

            // 1 saldo por (business, contact) — UNIQUE garante UPSERT seguro
            $table->unique(['business_id', 'contact_id'], 'uniq_vest_credito_biz_contact');
            $table->index('business_id', 'idx_vest_credito_business');
            $table->index('expira_em', 'idx_vest_credito_expira');

            // FK descomentar após validar schema em homolog
            // $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            // $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vestuario_creditos_cliente');
    }
};
