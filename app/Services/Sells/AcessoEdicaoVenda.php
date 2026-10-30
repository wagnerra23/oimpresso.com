<?php

declare(strict_types=1);

namespace App\Services\Sells;

use App\Transaction;
use App\Utils\TransactionUtil;
use Illuminate\Http\Exceptions\HttpResponseException;

/** Pré-condições comuns ao editor atual e à integração da V3 (UC-SEDIT-01..04). */
final class AcessoEdicaoVenda
{
    public function __construct(private TransactionUtil $util) {}

    public function carregar(int $id, int $businessId, mixed $editDays): Transaction
    {
        if (! auth()->user()->can('direct_sell.update') && ! auth()->user()->can('so.update')) {
            abort(403, 'Unauthorized action.');
        }

        // Transaction é legado sem global scope: resolver o tenant ANTES de consultar prazo
        // ou devolução impede que um id alheio revele sua existência por um 422 (ADR 0093).
        $venda = Transaction::where('business_id', $businessId)
            ->whereIn('type', ['sell', 'sales_order'])
            ->with(['price_group', 'types_of_service', 'media', 'media.uploaded_by_user'])
            ->findOrFail($id);

        if ($venda->type === 'sales_order' && ! auth()->user()->can('so.update')) {
            abort(403, 'Unauthorized action.');
        }

        if (! $this->util->canBeEdited($venda, $editDays)) {
            $this->recusar(__('messages.transaction_edit_not_allowed', ['days' => $editDays]));
        }

        if (Transaction::where('business_id', $businessId)->where('return_parent_id', $id)->exists()) {
            $this->recusar(__('lang_v1.return_exist'));
        }

        return $venda;
    }

    private function recusar(string $mensagem): never
    {
        $status = ['success' => 0, 'msg' => $mensagem];
        throw new HttpResponseException(request()->header('X-Inertia')
            ? response()->json($status, 422)
            : back()->with('status', $status));
    }
}
