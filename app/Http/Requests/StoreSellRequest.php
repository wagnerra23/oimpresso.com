<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest skeleton pra criar venda (Sells).
 *
 * D8.c Security — Onda Wave S. Extraido de SellController@store (linhas 1451 + 1554).
 *
 * ⛔ Tier 0 cautela: SellController NÃO foi editado nesta onda (biz=4 prod ROTA LIVRE,
 *    99% volume — risco regressão silenciosa). Type-hint Request → StoreSellRequest
 *    fica deferido pra próxima onda com canary 7d. Esta classe é o skeleton.
 *
 * Rules visíveis extraidas de $request->validate() inline no Controller:
 * - L1451: endpoint addPayment (sub-rota /sells/{id}/payment).
 * - L1554: endpoint criarOsPorVenda (sub-rota /sells/{id}/criar-os).
 *
 * NÃO move ainda o store() principal — depende de auditoria full do método (~500 linhas).
 *
 * @see app/Http/Controllers/SellController.php
 */
class StoreSellRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        if ($user->can('superadmin')) {
            return true;
        }

        return $user->can('sell.create');
    }

    public function rules(): array
    {
        // Rules visíveis em $request->validate() do Controller (SellController@addPayment + criarOsPorVenda).
        // Skeleton — store() principal será migrado em onda subsequente após canary 7d.
        return [
            // addPayment (L1451)
            'amount'     => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'method'     => ['sometimes', 'required', 'string', 'max:30'],
            'paid_on'    => ['nullable', 'date'],
            'note'       => ['nullable', 'string', 'max:500'],
            'account_id' => ['nullable', 'integer'],

            // criarOsPorVenda (L1554)
            'mode' => ['nullable', 'string', 'in:auto,single,per_line'],
        ];
    }
}
