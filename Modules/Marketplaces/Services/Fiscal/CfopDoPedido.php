<?php

declare(strict_types=1);

namespace Modules\Marketplaces\Services\Fiscal;

/**
 * CFOP da NF-e de um pedido de marketplace (US-MKT-009 · ADR 0439 D5 · SPEC §5).
 *
 * ⚠️ O mapeamento é PROVISÓRIO até [W] conferir com o contador (D5). Por isso os códigos vêm da
 * configuração (`marketplaces.nfe.cfop_*`) e a emissão automática nasce desligada.
 *
 * Venda em que o próprio vendedor envia (me1/me2/cross_docking/drop_off): 5102 na mesma UF,
 * 6102 em outra. Venda saída do estoque Full do ML (`fulfillment`) NÃO tem CFOP aqui: a nota da
 * venda Full depende da remessa/retorno em consignação (5910/1411 do SPEC §5) e fica para quando o
 * contador decidir — devolve null e a emissão é recusada com o motivo.
 */
final class CfopDoPedido
{
    public static function resolver(?string $ufEmitente, ?string $ufDestino, ?string $logistica): ?string
    {
        if ($logistica === 'fulfillment') {
            return null;
        }
        $origem = strtoupper(trim((string) $ufEmitente));
        $destino = strtoupper(trim((string) $ufDestino));
        if (strlen($origem) !== 2 || strlen($destino) !== 2) {
            return null;
        }

        return $origem === $destino
            ? (string) config('marketplaces.nfe.cfop_mesma_uf', '5102')
            : (string) config('marketplaces.nfe.cfop_outra_uf', '6102');
    }
}
