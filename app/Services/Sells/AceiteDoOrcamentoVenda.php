<?php

declare(strict_types=1);

namespace App\Services\Sells;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cliente aprova o orçamento da venda comum por um link (US-SELL-074).
 *
 * Mesmo desenho do aceite da gráfica (`Modules\ComunicacaoVisual\Services\AceiteDoOrcamento`,
 * US-COMVIS-023), sobre a venda (`transactions` em draft + quotation) em vez do orçamento da gráfica:
 *
 * Gerar: o vendedor pede o link de um orçamento aberto. Sai um token aleatório de 48 caracteres;
 * só o sha256 fica em `transaction_orcamento_aceites`, válido por `sells.orcamento_aceite.dias_validade`
 * dias. Gerar outro fecha o anterior. O token NÃO é o `invoice_token` do link legado
 * (`/quote/{token}`), que não vence.
 *
 * Mostrar: o que o cliente vê — empresa, número, itens com quantidade e preço de venda, total.
 * Nunca custo, margem, cliente ou telefone.
 *
 * Tier 0 (ADR 0093): o token resolve a empresa; tudo depois filtra pelo `business_id` do link.
 * Nenhum escopo é desligado: as consultas são `DB::table` com `business_id` explícito.
 * Não muda valor nem estoque.
 */
final class AceiteDoOrcamentoVenda
{
    /**
     * @return array{token: string, expira_em: Carbon}
     * @throws \DomainException venda que não é orçamento aberto
     * @throws ModelNotFoundException venda de outra empresa ou inexistente
     */
    public function gerar(int $businessId, int $transactionId, ?int $userId = null): array
    {
        return DB::transaction(function () use ($businessId, $transactionId, $userId) {
            $venda = DB::table('transactions')->where('business_id', $businessId)->where('id', $transactionId)
                ->where('type', 'sell')->lockForUpdate()->first()
                ?? throw (new ModelNotFoundException())->setModel(\App\Transaction::class, [$transactionId]);
            if (! self::ehOrcamentoAberto($venda)) {
                throw new \DomainException('Só orçamento em aberto vai ao cliente por link.');
            }

            $agora = now();
            DB::table('transaction_orcamento_aceites')->where('business_id', $businessId)
                ->where('transaction_id', $transactionId)->whereNull('decisao')->where('expira_em', '>', $agora)
                ->update(['expira_em' => $agora, 'updated_at' => $agora]);

            $token = Str::random(48);
            $expira = $agora->copy()->addDays(max(1, (int) config('sells.orcamento_aceite.dias_validade', 7)));
            DB::table('transaction_orcamento_aceites')->insert([
                'business_id' => $businessId, 'transaction_id' => $transactionId, 'token_hash' => self::hash($token),
                'expira_em' => $expira, 'created_by' => $userId, 'created_at' => $agora, 'updated_at' => $agora,
            ]);

            return ['token' => $token, 'expira_em' => $expira];
        });
    }

    /**
     * O que a página pública mostra. Null para token desconhecido.
     *
     * @return array<string, mixed>|null
     */
    public function paraCliente(string $token): ?array
    {
        $link = DB::table('transaction_orcamento_aceites')->where('token_hash', self::hash($token))->first();
        if ($link === null) {
            return null;
        }
        $biz = (int) $link->business_id;
        $venda = DB::table('transactions')->where('business_id', $biz)->where('id', $link->transaction_id)->first();
        if ($venda === null) {
            return null;
        }
        $itens = DB::table('transaction_sell_lines as l')
            ->join('products as p', function ($j) use ($biz) {
                $j->on('p.id', '=', 'l.product_id')->where('p.business_id', $biz);
            })
            ->where('l.transaction_id', $venda->id)->whereNull('l.parent_sell_line_id')
            ->orderBy('l.id')->get(['p.name', 'l.quantity', 'l.unit_price_inc_tax']);

        return [
            'empresa' => (string) DB::table('business')->where('id', $biz)->value('name'),
            'numero' => (string) $venda->invoice_no,
            'data_emissao' => $venda->transaction_date,
            'expira_em' => $link->expira_em,
            'itens' => $itens->map(fn ($i) => [
                'descricao' => (string) $i->name,
                'quantidade' => (float) $i->quantity,
                'preco_unitario' => round((float) $i->unit_price_inc_tax, 2),
                'subtotal' => round((float) $i->quantity * (float) $i->unit_price_inc_tax, 2),
            ])->all(),
            'frete' => round((float) ($venda->shipping_charges ?? 0), 2),
            'total' => round((float) $venda->final_total, 2),
            'situacao' => self::situacao($link, $venda),
            'decisao' => $link->decisao,
        ];
    }

    /** aberto | decidido | vencido | indisponivel */
    public static function situacao(object $link, object $venda): string
    {
        if ($link->decisao !== null) {
            return 'decidido';
        }
        if (Carbon::parse($link->expira_em)->lessThanOrEqualTo(now())) {
            return 'vencido';
        }

        return self::ehOrcamentoAberto($venda) ? 'aberto' : 'indisponivel';
    }

    public static function ehOrcamentoAberto(object $venda): bool
    {
        return ($venda->status ?? null) === 'draft' && ($venda->sub_status ?? null) === 'quotation'
            && ($venda->deleted_at ?? null) === null;
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
