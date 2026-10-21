<?php

namespace App\Services\Sells;

use App\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Termômetro de acesso da proposta (US-SELL-070 · UC-FLX-04).
 *
 * Grava, em `transaction_quote_accesses`, cada abertura do link público do orçamento.
 * Não conta: HEAD, prefetch do navegador (`Purpose`/`Sec-Purpose: prefetch`), venda que não é
 * orçamento, e usuário logado da própria empresa (é o vendedor conferindo o link).
 *
 * Nunca derruba a página: qualquer falha vira um aviso no log com só os ids (sem IP, sem token).
 */
class QuoteAccessRecorder
{
    public function record(Transaction $transaction, Request $request): bool
    {
        try {
            if (! $this->deveContar($transaction, $request)) {
                return false;
            }

            $agora = now();
            $chave = ['transaction_id' => (int) $transaction->id];

            DB::table('transaction_quote_accesses')->insertOrIgnore($chave + [
                'business_id' => (int) $transaction->business_id,
                'open_count' => 0,
                'created_at' => $agora,
                'updated_at' => $agora,
            ]);

            $linha = DB::table('transaction_quote_accesses')
                ->where('business_id', (int) $transaction->business_id)
                ->where($chave);

            (clone $linha)->update([
                'open_count' => DB::raw('open_count + 1'),
                'last_opened_at' => $agora,
                'updated_at' => $agora,
            ]);
            (clone $linha)->whereNull('first_opened_at')->update(['first_opened_at' => $agora]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('quote_access.record_failed', [
                'transaction_id' => (int) $transaction->id,
                'business_id' => (int) $transaction->business_id,
                'error' => class_basename($e),
            ]);

            return false;
        }
    }

    private function deveContar(Transaction $transaction, Request $request): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        $proposito = strtolower((string) ($request->header('Sec-Purpose') ?? $request->header('Purpose') ?? ''));
        if (str_contains($proposito, 'prefetch')) {
            return false;
        }

        if ($transaction->status !== 'draft' || $transaction->sub_status !== 'quotation') {
            return false;
        }

        $user = auth()->user();
        if ($user !== null && (int) $user->business_id === (int) $transaction->business_id) {
            return false;
        }

        return true;
    }
}
