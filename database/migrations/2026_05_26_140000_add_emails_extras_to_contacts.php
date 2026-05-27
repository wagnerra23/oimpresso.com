<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onda 1 PR B' 2026-05-26 — Pedido Daniela @ Martinho.
 *
 * Adiciona 2 colunas de email diferenciado em `contacts`:
 *   - email_billing: email do contato comercial / cobrança
 *   - email_nfe:     email pra envio de NF-e / NFS-e (área fiscal)
 *
 * Daniela explicou que o email principal já existe (campo `email`) mas é o
 * pessoal — quando emite NF-e, precisa mandar pro contador (email_nfe), e o
 * comercial vai pro vendedor (email_billing).
 *
 * Telefones: SEM migration — UPOS já tem `mobile`+`landline`+`alternate_number`,
 * Wave B drawer adicionou `tel2` (PR #1422). PR B' apenas EXPÕE
 * `alternate_number` no Edit/Drawer pra Daniela cadastrar 3º telefone.
 *
 * Idempotente via Schema::hasColumn — seguro de rodar 2x.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            if (! Schema::hasColumn('contacts', 'email_billing')) {
                $table->string('email_billing', 320)->nullable()->after('email');
            }
            if (! Schema::hasColumn('contacts', 'email_nfe')) {
                $table->string('email_nfe', 320)->nullable()->after('email_billing');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            if (Schema::hasColumn('contacts', 'email_nfe')) {
                $table->dropColumn('email_nfe');
            }
            if (Schema::hasColumn('contacts', 'email_billing')) {
                $table->dropColumn('email_billing');
            }
        });
    }
};
