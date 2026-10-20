<?php

declare(strict_types=1);

namespace Modules\Producao\Services;

use Illuminate\Support\Facades\DB;

/**
 * Data prometida da venda aprovada (UC-FLX-06 · US-PCP-028). Decisão [W] 2026-10-20: a data
 * prometida fica na venda (`transactions.due_date`, PROJETO_DT_FIM do Office Comercial) e vai
 * para a OS (`pcp_ordens.prazo_prometido`).
 *
 * Roda depois de "Cliente aprova" criar as OS (`CriarOsDaVendaJob`):
 * - venda sem data → grava a estimativa do `PrazoPelaFila` (dias úteis no turno);
 * - venda com data → **não sobrescreve** a data que o vendedor digitou; só a copia;
 * - OS da venda sem prazo recebem a data da venda. Repetir é inócuo.
 *
 * Tier 0: lê e grava só no `$businessId` recebido; venda de outra empresa → null, nada muda.
 */
final class PrometerPrazoDaVenda
{
    public function __construct(private readonly PrazoPelaFila $prazo) {}

    /** @return string|null a data prometida (Y-m-d) que ficou na venda */
    public function gravar(int $businessId, int $transactionId): ?string
    {
        $venda = fn () => DB::table('transactions')->where('business_id', $businessId)->where('id', $transactionId)->where('type', 'sell');
        if (! $venda()->exists()) {
            return null;
        }

        if ($venda()->value('due_date') === null) {
            $estimativa = $this->prazo->daVenda($businessId, $transactionId)['data_prevista'] ?? null;
            if ($estimativa !== null) {
                // whereNull na própria escrita: se o vendedor gravou no meio do cálculo, a dele fica.
                $venda()->whereNull('due_date')->update(['due_date' => $estimativa]);
            }
        }

        $data = $venda()->value('due_date');
        if ($data === null) {
            return null;
        }
        $data = substr((string) $data, 0, 10);
        DB::table('pcp_ordens')->where('business_id', $businessId)->where('transaction_id', $transactionId)
            ->whereNull('prazo_prometido')->update(['prazo_prometido' => $data, 'updated_at' => now()]);

        return $data;
    }
}
