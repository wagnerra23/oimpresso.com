<?php

declare(strict_types=1);

namespace Modules\Producao\Services;

use App\Contracts\Fabricacao\InsumosDaReceita;
use App\Contracts\Producao\ReservaDeInsumo;
use App\Domain\Fsm\Models\StockReservation;
use App\Scopes\ScopeByBusiness;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Entities\Ordem;

/**
 * Reserva o insumo na venda, baixa real na produção (US-PCP-031 · UC-FLX-14) — mexe em ESTOQUE (regra
 * mestre, proibicoes.md). Decisão [W] 2026-10-20: *"Reserva insumo na venda, baixa real na produção"*.
 *
 * Para o item feito sob encomenda (banner, placa, adesivo):
 *   - a venda aprovada cria a OS do item (CriarOsDaVenda) e ela RESERVA o insumo da receita do produto
 *     (`App\Contracts\Fabricacao\InsumosDaReceita`): quantidade = receita por unidade × quantidade da OS;
 *   - o apontamento da etapa BAIXA a quantidade real (ConsumoMaterialService) e abate da reserva da OS —
 *     nunca abate mais do que está reservado; o que passa da reserva baixa normalmente;
 *   - a OS concluída ou cancelada LIBERA o que sobrou; a venda cancelada libera todas as reservas dela.
 * A reserva nunca mexe no saldo (`variation_location_details`): quem baixa é só o apontamento. Não há
 * segunda baixa em lugar nenhum.
 *
 * "Sob encomenda" = o item gera OS (tem roteiro) E o produto vendido NÃO controla estoque
 * (`products.enable_stock = 0`) E tem receita. Produto que controla estoque (revenda) não reserva nada:
 * o núcleo segue baixando a peça na venda finalizada (SellPosController). Produção para estoque
 * (ordem de produção do Manufacturing) não passa por aqui.
 *
 * Chave por empresa em `business.common_settings['producao_reserva_insumo']`, DESLIGADA por padrão:
 * ninguém muda no deploy. Sem backfill: só reserva a OS criada com a chave ligada. Liberar não depende
 * da chave (libera o que existir). `expires_at` nulo: a OS pode levar mais que o TTL das reservas do
 * motor (ExpireStaleReservationsJob só expira quem tem data). Tier 0: tudo pelo `$businessId` recebido.
 *
 * `qty_reserved` é o que AINDA está reservado: o apontamento o reduz; chegando a zero a reserva vira
 * `consumed`. O que saiu de fato fica em `pcp_consumos` (US-PCP-021).
 */
final class ReservaDeInsumoDaOs implements ReservaDeInsumo
{
    public const CHAVE = 'producao_reserva_insumo';

    private static ?bool $temColuna = null;

    public function ligada(int $businessId): bool
    {
        $ajustes = json_decode((string) DB::table('business')->where('id', $businessId)->value('common_settings'), true);

        return is_array($ajustes) && ! empty($ajustes[self::CHAVE]);
    }

    /**
     * Liga ou desliga a reserva na empresa (interruptor da aba Fluxos da empresa, PATCH /fluxos/reserva).
     * Mexe só nesta chave: lê e regrava `common_settings` sob trava, preservando as outras chaves.
     * Desligar não libera reserva que já existe — ela segue até a OS concluir ou cancelar.
     */
    public function mudar(int $businessId, bool $ligada): void
    {
        DB::transaction(function () use ($businessId, $ligada) {
            $bruto = DB::table('business')->where('id', $businessId)->lockForUpdate()->value('common_settings');
            $ajustes = json_decode((string) $bruto, true);
            $ajustes = is_array($ajustes) ? $ajustes : [];
            if ($ligada) {
                $ajustes[self::CHAVE] = 1;
            } else {
                unset($ajustes[self::CHAVE]);
            }
            DB::table('business')->where('id', $businessId)->update(['common_settings' => json_encode($ajustes)]);
        });
    }

    /** Na criação da OS (venda aprovada). @return int reservas criadas agora */
    public function reservar(int $businessId, Ordem $ordem): int
    {
        if (! $this->pronta() || ! app()->bound(InsumosDaReceita::class) || ! $this->ligada($businessId) || $ordem->variation_id === null) {
            return 0;
        }
        $controlaEstoque = DB::table('products')->where('business_id', $businessId)->where('id', $ordem->product_id)->value('enable_stock');
        if ($controlaEstoque === null || (int) $controlaEstoque === 1) {
            return 0; // revenda: o núcleo baixa a peça vendida; nada a reservar
        }
        $local = DB::table('transactions')->where('business_id', $businessId)->where('id', $ordem->transaction_id)->value('location_id');
        if ($local === null) {
            return 0;
        }

        $criadas = 0;
        foreach (app(InsumosDaReceita::class)->paraQuantidade($businessId, (int) $ordem->variation_id, (string) $ordem->quantidade) as $insumo) {
            if ($this->dezMilesimos($insumo['quantidade']) <= 0) {
                continue;
            }
            // Escopo global trocado por business_id explícito: roda em fila, sem sessão (ADR 0093).
            $reserva = StockReservation::withoutGlobalScope(ScopeByBusiness::class)->firstOrCreate(
                ['business_id' => $businessId, 'pcp_ordem_id' => $ordem->id, 'variation_id' => $insumo['variation_id']],
                ['transaction_id' => $ordem->transaction_id, 'product_id' => $insumo['product_id'], 'location_id' => (int) $local,
                    'qty_reserved' => $insumo['quantidade'], 'status' => StockReservation::STATUS_ACTIVE, 'expires_at' => null],
            );
            $criadas += $reserva->wasRecentlyCreated ? 1 : 0;
        }

        return $criadas;
    }

    /**
     * Depois da baixa real do apontamento, dentro da mesma transação: abate da reserva da OS no máximo o
     * que ela tem. @return ?string o que ficou reservado (null quando a OS não tinha reserva do insumo)
     */
    public function abater(int $businessId, int $ordemId, int $variationId, string $quantidade): ?string
    {
        if (! $this->pronta()) {
            return null;
        }
        $reserva = StockReservation::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $businessId)
            ->where('pcp_ordem_id', $ordemId)->where('variation_id', $variationId)
            ->where('status', StockReservation::STATUS_ACTIVE)->lockForUpdate()->first();
        if (! $reserva) {
            return null;
        }
        $resto = max(0, $this->dezMilesimos((string) $reserva->qty_reserved) - $this->dezMilesimos($quantidade));
        $reserva->update(['qty_reserved' => $this->texto($resto),
            'status' => $resto === 0 ? StockReservation::STATUS_CONSUMED : StockReservation::STATUS_ACTIVE]);

        return $this->texto($resto);
    }

    /** OS concluída ou cancelada: o que sobrou da reserva dela volta a ficar livre. */
    public function liberar(int $businessId, int $ordemId): int
    {
        return $this->pronta() ? $this->ativas($businessId)->where('pcp_ordem_id', $ordemId)
            ->update(['status' => StockReservation::STATUS_RELEASED]) : 0;
    }

    /** Venda cancelada: libera as reservas de insumo de todas as OS dela. */
    public function liberarDaVenda(int $businessId, int $transactionId): int
    {
        return $this->pronta() ? $this->ativas($businessId)->where('transaction_id', $transactionId)->whereNotNull('pcp_ordem_id')
            ->update(['status' => StockReservation::STATUS_RELEASED]) : 0;
    }

    /** @return \Illuminate\Database\Eloquent\Builder<StockReservation> */
    private function ativas(int $businessId): \Illuminate\Database\Eloquent\Builder
    {
        return StockReservation::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $businessId)
            ->where('status', StockReservation::STATUS_ACTIVE);
    }

    private function pronta(): bool
    {
        return self::$temColuna ??= Schema::hasColumn('stock_reservations', 'pcp_ordem_id');
    }

    /** Decimal de 4 casas em décimos de milésimo inteiros, sem float (regra mestre). */
    private function dezMilesimos(string $valor): int
    {
        [$inteiro, $fracao] = array_pad(explode('.', trim($valor) === '' ? '0' : trim($valor), 2), 2, '');

        return (int) $inteiro * 10000 + (int) substr(str_pad($fracao, 4, '0'), 0, 4);
    }

    private function texto(int $n): string
    {
        return sprintf('%d.%04d', intdiv($n, 10000), $n % 10000);
    }
}
