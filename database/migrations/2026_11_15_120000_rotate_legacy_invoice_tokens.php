<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rotação dos tokens do link público da venda (/invoice, /quote, /pay) — decisão [W] 2026-11-15.
 *
 * O gerador antigo (`Util::generateToken`) produzia 32 caracteres hex. O novo produz 64 caracteres
 * aleatórios. Esta migration apaga só os tokens no formato antigo (comprimento 32): o próximo link
 * pedido no painel (`Util::getInvoiceUrl` / `getInvoicePaymentLink`) gera um token novo sozinho.
 *
 * Efeito visível: links antigos já enviados a clientes param de abrir. Para reenviar, gera-se um link
 * novo no painel. Idempotente: rodar de novo não toca tokens novos (64 caracteres).
 *
 * down() não restaura os tokens antigos — de propósito; não há como nem por quê.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transactions') || ! Schema::hasColumn('transactions', 'invoice_token')) {
            return;
        }

        // Em lotes por id para não segurar lock longo na tabela de vendas.
        do {
            $ids = DB::table('transactions')
                ->whereNotNull('invoice_token')
                ->whereRaw('LENGTH(invoice_token) = 32')
                ->orderBy('id')
                ->limit(1000)
                ->pluck('id');

            if ($ids->isNotEmpty()) {
                DB::table('transactions')->whereIn('id', $ids)->update(['invoice_token' => null]);
            }
        } while ($ids->count() === 1000);
    }

    public function down(): void
    {
        // Irreversível por natureza: os tokens antigos não são recuperáveis (e não devem ser).
    }
};
