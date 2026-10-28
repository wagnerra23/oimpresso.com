<?php

declare(strict_types=1);

namespace App\Services\Sells;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Cupom de desconto por código no caixa — US-SELL-076.
 *
 * A promoção (produto, categoria, marca ou período) já existe em `discounts` e chega ao
 * carrinho por linha. O cupom vale sobre o carrinho inteiro, e a regra de acúmulo é do cupom:
 *
 *  - `acumula_promocao = true`: o cupom incide sobre o subtotal JÁ com a promoção;
 *  - `acumula_promocao = false` (padrão): o cliente fica com o MAIOR dos dois. O cupom, calculado
 *    sobre o subtotal sem promoção, só entra se for maior; aí substitui a promoção.
 *
 * Desconto nunca passa da base. Arredonda a 2 casas. `avaliar` não grava nada; `consumir` gasta
 * um uso de forma atômica (respeitando o limite) e é o que a venda chama ao ser gravada.
 */
final class CupomDaVenda
{
    public static function ligado(): bool
    {
        return (bool) config('sells.cupom.ativo', false);
    }

    /**
     * @param  float  $subtotal  carrinho sem nenhuma promoção
     * @param  float  $promocao  desconto de promoção já dado nas linhas
     * @return array{ok: bool, motivo: ?string, desconto: float, substitui_promocao: bool, cupom_id: ?int}
     */
    public function avaliar(int $businessId, string $codigo, float $subtotal, float $promocao, CarbonInterface $agora): array
    {
        $c = DB::table('sell_coupons')->where('business_id', $businessId)
            ->where('codigo', self::normalizar($codigo))->where('ativo', 1)->first();

        $recusa = fn (string $motivo) => ['ok' => false, 'motivo' => $motivo, 'desconto' => 0.0, 'substitui_promocao' => false, 'cupom_id' => $c?->id ? (int) $c->id : null];

        if (! $c) {
            return $recusa('invalido');
        }
        if (($c->valido_de && $agora->lt($c->valido_de)) || ($c->valido_ate && $agora->gt($c->valido_ate))) {
            return $recusa('fora_da_validade');
        }
        if ($c->limite_usos !== null && (int) $c->usos >= (int) $c->limite_usos) {
            return $recusa('esgotado');
        }
        if ($c->valor_minimo !== null && $subtotal < (float) $c->valor_minimo) {
            return $recusa('abaixo_do_minimo');
        }

        $acumula = (bool) $c->acumula_promocao;
        $base = $acumula ? max(0.0, $subtotal - $promocao) : $subtotal;
        $desconto = $c->tipo === 'valor' ? (float) $c->valor : $base * (float) $c->valor / 100;
        $desconto = round(min($desconto, $base), 2);

        if (! $acumula && $desconto <= round($promocao, 2)) {
            return $recusa('promocao_maior');
        }

        return ['ok' => true, 'motivo' => null, 'desconto' => $desconto, 'substitui_promocao' => ! $acumula && $promocao > 0, 'cupom_id' => (int) $c->id];
    }

    /** Gasta um uso. false = esgotou entre a avaliação e a gravação (a venda deve recusar). */
    public function consumir(int $businessId, int $cupomId): bool
    {
        return DB::table('sell_coupons')->where('id', $cupomId)->where('business_id', $businessId)
            ->where(fn ($q) => $q->whereNull('limite_usos')->orWhereColumn('usos', '<', 'limite_usos'))
            ->increment('usos') === 1;
    }

    public static function normalizar(string $codigo): string
    {
        return mb_strtoupper(trim($codigo));
    }
}
