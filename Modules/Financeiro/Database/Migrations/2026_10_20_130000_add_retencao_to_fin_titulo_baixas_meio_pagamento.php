<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * US-FIN-072 · UC-FLX-30 — meio de baixa `retencao`: o ISS/PIS/COFINS/CSLL retidos pelo tomador
 * na NFS-e quitam parte do título sem dinheiro entrar (decisão [W] 2026-10-20: retenção é BAIXA,
 * não redução do valor do título). Só ACRESCENTA o valor ao fim do enum — linhas existentes
 * não mudam. Declarado em memory/dominio/financeiro.md.
 *
 * MySQL-only (enum físico); em outro driver não há enum a alterar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fin_titulo_baixas') || DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE fin_titulo_baixas MODIFY COLUMN meio_pagamento ENUM('dinheiro','pix','boleto','cartao_credito','cartao_debito','transferencia','cheque','compensacao','outro','retencao') NOT NULL");
    }

    public function down(): void
    {
        // Append-only: tirar o valor do enum falharia com baixa de retenção gravada.
    }
};
