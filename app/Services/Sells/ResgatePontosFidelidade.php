<?php

declare(strict_types=1);

namespace App\Services\Sells;

use App\Utils\TransactionUtil;
use Illuminate\Support\Facades\DB;

/**
 * Resgate de pontos de fidelidade na venda (US-SELL-079) — a conferência que o servidor faz antes
 * de gravar. Até aqui quem conferia era o JavaScript da Blade antiga (`public/js/pos.js`): o
 * `store` gravava `rp_redeemed` e `rp_redeemed_amount` como vinham da tela.
 *
 * Regras, as mesmas da Blade e de `TransactionUtil::getRewardRedeemDetails`:
 *  - a empresa tem os pontos ligados (`business.enable_rp`);
 *  - o cliente é da empresa e não é o "Cliente padrão";
 *  - pontos inteiros, entre `min_redeem_point` e o disponível (já limitado por `max_redeem_point`);
 *  - a venda vale pelo menos `min_order_total_for_redeem`;
 *  - valor do resgate = pontos × `redeem_amount_per_unit_rp`, arredondado a 2 casas. O valor que a
 *    tela mandou tem de bater (±0,01), porque o `final_total` da venda já vem com ele descontado.
 *
 * A conta do valor é feita em inteiros (décimos de milésimo de real), sem ponto flutuante.
 * Tier 0 (ADR 0093): empresa e cliente lidos com o `business_id` da sessão.
 */
final class ResgatePontosFidelidade
{
    public function __construct(private readonly TransactionUtil $transactionUtil) {}

    /**
     * @return array{pontos:int, valor:string}  o que gravar (0 e "0.00" quando não há resgate)
     *
     * @throws ResgatePontosException
     */
    public function conferir(int $businessId, ?int $contactId, mixed $pontos, mixed $valorInformado, float $totalDaVenda): array
    {
        $pontos = (int) ($pontos ?? 0);
        if ($pontos <= 0) {
            return ['pontos' => 0, 'valor' => '0.00'];
        }

        $empresa = DB::table('business')->where('id', $businessId)
            ->first(['enable_rp', 'redeem_amount_per_unit_rp', 'min_order_total_for_redeem', 'min_redeem_point']);
        if ($empresa === null || (int) $empresa->enable_rp !== 1) {
            throw new ResgatePontosException('Os pontos de fidelidade não estão ligados nesta empresa.');
        }
        $cliente = $contactId ? DB::table('contacts')->where('business_id', $businessId)->where('id', $contactId)->first(['id', 'is_default']) : null;
        if ($cliente === null || (int) $cliente->is_default === 1) {
            throw new ResgatePontosException('Escolha um cliente cadastrado para resgatar pontos.');
        }
        if (! empty($empresa->min_redeem_point) && $pontos < (int) $empresa->min_redeem_point) {
            throw new ResgatePontosException(sprintf('O resgate mínimo é de %d pontos.', (int) $empresa->min_redeem_point));
        }
        $disponivel = (int) ($this->transactionUtil->getRewardRedeemDetails($businessId, (int) $contactId)['points'] ?? 0);
        if ($pontos > $disponivel) {
            throw new ResgatePontosException(sprintf('O cliente pode resgatar até %d pontos nesta venda.', $disponivel));
        }
        if (! empty($empresa->min_order_total_for_redeem) && $totalDaVenda < (float) $empresa->min_order_total_for_redeem) {
            throw new ResgatePontosException('A venda não atinge o valor mínimo para resgatar pontos.');
        }

        $valor = self::valorDoResgate($pontos, (string) $empresa->redeem_amount_per_unit_rp);
        if (abs((float) $valor - (float) ($valorInformado ?? 0)) > 0.01) {
            throw new ResgatePontosException(sprintf(
                'O resgate de %d pontos vale R$ %s, mas a venda foi calculada com outro valor. Atualize a venda e salve de novo.',
                $pontos,
                number_format((float) $valor, 2, ',', '.'),
            ));
        }

        return ['pontos' => $pontos, 'valor' => $valor];
    }

    /**
     * O que a tela de venda precisa para mostrar o resgate; null esconde (flag desligada ou empresa
     * sem pontos). O saldo do cliente vem de `POST /sells/pos/get-reward-details`, que já existe.
     *
     * @return array{nome:string, valor_ponto:string, minimo_venda:string, minimo_pontos:int}|null
     */
    public static function propsDaTela(int $businessId): ?array
    {
        if (! (bool) config('sells.resgate_pontos.enabled', false)) {
            return null;
        }
        $e = DB::table('business')->where('id', $businessId)
            ->first(['enable_rp', 'rp_name', 'redeem_amount_per_unit_rp', 'min_order_total_for_redeem', 'min_redeem_point']);
        if ($e === null || (int) $e->enable_rp !== 1 || (float) $e->redeem_amount_per_unit_rp <= 0) {
            return null;
        }

        return [
            'nome' => (string) ($e->rp_name ?: 'Pontos'),
            'valor_ponto' => (string) $e->redeem_amount_per_unit_rp,
            'minimo_venda' => (string) ($e->min_order_total_for_redeem ?? '0'),
            'minimo_pontos' => (int) ($e->min_redeem_point ?? 0),
        ];
    }

    /** pontos × valor do ponto, arredondado a 2 casas (meio para cima), sem ponto flutuante. */
    public static function valorDoResgate(int $pontos, string $valorDoPonto): string
    {
        [$inteiro, $fracao] = array_pad(explode('.', trim($valorDoPonto), 2), 2, '');
        $decimilesimos = (int) $inteiro * 10000 + (int) str_pad(substr($fracao, 0, 4), 4, '0');
        $total = $pontos * $decimilesimos;           // em 1/10000 de real
        $centavos = intdiv($total + 50, 100);        // meio para cima
        return sprintf('%d.%02d', intdiv($centavos, 100), $centavos % 100);
    }
}
