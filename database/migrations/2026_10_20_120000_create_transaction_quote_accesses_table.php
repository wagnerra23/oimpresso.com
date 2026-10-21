<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Termômetro de acesso da proposta (US-SELL-070 · UC-FLX-04).
 *
 * Tabela-ponte: não altera `transactions` (core UltimatePOS). Uma linha por orçamento:
 * primeira e última abertura do link público `/quote/{token}`, quantas aberturas, e a
 * tarefa de retorno criada quando o orçamento passou 48 h sem abertura (idempotência).
 * Sem IP e sem user agent: o cliente não é identificado (LGPD).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transaction_quote_accesses')) {
            return;
        }

        Schema::create('transaction_quote_accesses', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->index();
            $t->unsignedInteger('transaction_id');
            $t->dateTime('first_opened_at')->nullable();
            $t->dateTime('last_opened_at')->nullable();
            $t->unsignedInteger('open_count')->default(0);
            $t->unsignedBigInteger('followup_schedule_id')->nullable();
            $t->dateTime('followup_created_at')->nullable();
            $t->timestamps();
            $t->unique('transaction_id', 'tx_quote_access_tx_uq');
            $t->index(['business_id', 'first_opened_at'], 'tx_quote_access_biz_open_idx');
            $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            $t->foreign('transaction_id')->references('id')->on('transactions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_quote_accesses');
    }
};
