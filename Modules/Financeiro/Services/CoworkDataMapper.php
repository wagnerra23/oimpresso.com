<?php

declare(strict_types=1);

namespace Modules\Financeiro\Services;

use Illuminate\Support\Carbon;
use Modules\Financeiro\Models\Titulo;

/**
 * Mapeia dados reais Eloquent (Titulo + TituloBaixa) pro shape JSX Cowork
 * canon esperado pelo `public/cowork-preview/financeiro-data.jsx`:
 *
 *   {
 *     id: "R-2641",
 *     kind: "receivable" | "payable",
 *     desc: "Banner lona 4×1m — promo dia das mães · #V-7832",
 *     party: "Padaria Pão Quente",
 *     category: "Banner",
 *     amount: 480.00,
 *     due: "2026-05-12",          // ISO date string (JS Date no frontend)
 *     paid_at: "2026-05-13" | null,
 *     channel: "PIX" | "Boleto",
 *     invoice: "NF 4112" | "NFe 8422" | null,
 *     status: "recebido" | "pago" | "atrasado" | "vencendo" | "pendente",
 *   }
 *
 * Wagner regra 2026-05-18: Mock canon serve sem adaptação, mas dados REAIS
 * de Larissa @ biz=4 são injetados sobrescrevendo `window.FIN_ROWS` mock.
 *
 * Tier 0 multi-tenant: filtra business_id explícito da session.
 */
class CoworkDataMapper
{
    /**
     * Coleta lançamentos do business + período (default últimos 6 meses)
     * + mapeia pro shape JSX Cowork.
     *
     * @return array{FIN_TODAY: string, FIN_ROWS: array<int, array<string, mixed>>}
     */
    public static function collect(int $businessId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $today = now()->startOfDay();
        $from ??= (clone $today)->subMonths(6)->startOfDay();
        $to   ??= (clone $today)->addMonths(2)->endOfDay();

        $titulos = Titulo::query()
            ->where('business_id', $businessId)
            ->whereBetween('vencimento', [$from->toDateString(), $to->toDateString()])
            ->whereNull('deleted_at')
            ->whereIn('status', ['aberto', 'parcial', 'quitado'])
            ->with([
                'categoria:id,nome',
                'baixas' => fn ($q) => $q->orderByDesc('data_baixa'),
            ])
            ->orderBy('vencimento')
            ->limit(500)
            ->get();

        $rows = $titulos->map(function (Titulo $t) use ($today): array {
            $kind = $t->tipo === 'receber' ? 'receivable' : 'payable';
            $idPrefix = $kind === 'receivable' ? 'R-' : 'P-';
            $paidBaixa = $t->baixas->where('estorno_de_id', null)->first();
            $paidAt = $paidBaixa?->data_baixa;
            $status = self::deriveStatus($t, $today, $paidAt);

            // Descrição preserva refs cruzadas (#V- #BL- #OS-) se existirem em
            // cliente_descricao ou observacoes (Cowork FinCrossLinkify lê delas).
            $desc = trim((string) ($t->cliente_descricao ?? $t->descricao ?? ''));
            if (! empty($t->observacoes)) {
                $desc = $desc !== '' ? "{$desc} · {$t->observacoes}" : (string) $t->observacoes;
            }

            return [
                'id'       => $idPrefix . $t->id,
                'kind'     => $kind,
                'desc'     => $desc !== '' ? $desc : ($kind === 'receivable' ? 'Recebimento' : 'Pagamento'),
                'party'    => (string) ($t->cliente_descricao ?? '—'),
                'category' => (string) ($t->categoria?->nome ?? 'Sem categoria'),
                'amount'   => (float) $t->valor_total,
                'due'      => $t->vencimento?->toDateString(),
                'paid_at'  => $paidAt?->toDateString(),
                'channel'  => self::guessChannel($t),
                'invoice'  => $t->numero ? "NF {$t->numero}" : null,
                'status'   => $status,
            ];
        })->values()->all();

        return [
            'FIN_TODAY' => $today->toDateString(),
            'FIN_ROWS'  => $rows,
        ];
    }

    private static function deriveStatus(Titulo $t, Carbon $today, ?Carbon $paidAt): string
    {
        $kind = $t->tipo === 'receber' ? 'receivable' : 'payable';
        if ($paidAt !== null) {
            return $kind === 'receivable' ? 'recebido' : 'pago';
        }
        $venc = $t->vencimento;
        if ($venc === null) {
            return 'pendente';
        }
        $delta = $venc->diffInDays($today, false);
        if ($delta > 0) {
            return 'atrasado';
        }
        if ($delta >= -3) {
            return 'vencendo';
        }
        return 'pendente';
    }

    private static function guessChannel(Titulo $t): string
    {
        // Heurística simples — banco emisor primário do business é Inter (Wagner ROTA LIVRE).
        // PIX se valor pequeno (< R$ [redacted Tier 0]), Boleto caso contrário.
        return ($t->valor_total ?? 0) < 500.0 ? 'PIX' : 'Boleto';
    }
}
