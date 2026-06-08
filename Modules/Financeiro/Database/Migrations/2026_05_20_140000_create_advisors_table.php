<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onda 31 (2026-05-20) — US-FIN-037 Portal Advisor Contadores parceiros (Fase 1 MVP).
 *
 * Cria a tabela GLOBAL `advisors` (contadores parceiros, cross-tenant — um
 * mesmo contador atende N businesses). NÃO contém `business_id` (intencional,
 * por isso documentado aqui — exceção ADR 0093 multi-tenant Tier 0). O scope
 * multi-tenant fica na tabela `advisor_business_access` que correlaciona
 * advisor ↔ business via grant explícito do owner.
 *
 * Diferencial Conta Azul KILLER (DC2 Wagner 2026-05-19) — contador recomenda
 * o oimpresso ao cliente PJ e tem dashboard único pra ver N clientes seus.
 */
class CreateAdvisorsTable extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('advisors')) {
            Schema::create('advisors', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('cnpj_contador', 14)->unique()->comment('CNPJ contador 14 dígitos numéricos');
                $table->string('nome', 200);
                $table->string('email', 191)->unique();
                // Hash de senha pra guard custom `web-advisor` (login isolado do user UPos).
                $table->string('password_hash', 255)->nullable()->comment('bcrypt — null = ainda não definiu senha');
                $table->string('telefone', 20)->nullable();
                $table->string('referral_code', 8)->unique()->comment('código compartilhável /advisors/register?ref=XXXX');
                $table->boolean('ativo')->default(true);
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('ativo');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('advisors');
    }
}
