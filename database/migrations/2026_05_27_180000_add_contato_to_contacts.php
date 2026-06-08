<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix Daniela 2026-05-27 — drawer Cliente input "Contato principal" era
 * silencioso (validator aceitava `contato` mas nao havia coluna destino;
 * Eloquent::update jogava fora).
 *
 * Daniela @ Martinho (Onda 1 PR B') cadastrou Heinig Pre-Moldados e nao
 * conseguiu salvar nome do responsavel. Frontend ja envia ha tempos.
 *
 * Schema:
 *   contacts.contato VARCHAR(100) NULL
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGAVEL): preservado -- `business_id`
 * scope herdado da tabela contacts (nao precisa novo indice).
 *
 * Idempotente: hasColumn check pra ambiente onde migration ja rodou.
 *
 * Refs: ADR 0179 (drawer 760) · session 2026-05-27 (auditoria drawer + Daniela).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('contacts', 'contato')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->string('contato', 100)
                    ->nullable()
                    ->after('cargo')
                    ->comment('Nome do responsavel principal (PJ) — drawer IdentificacaoTab. Daniela 2026-05-27.');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contacts', 'contato')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->dropColumn('contato');
            });
        }
    }
};
